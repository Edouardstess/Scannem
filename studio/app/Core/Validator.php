<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Rule-string validator for server-side validation.
 *
 * Client-side validation exists for ergonomics only; every rule here runs on
 * the server, and controllers reject input that does not pass.
 */
final class Validator
{
    /** @var array<string, string> field => first error message */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    private bool $hasRun = false;

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = [],
        private array $labels = []
    ) {
    }

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    public static function make(array $data, array $rules, array $messages = [], array $labels = []): self
    {
        return new self($data, $rules, $messages, $labels);
    }

    /**
     * Run the rules, once.
     *
     * The result is memoised: callers routinely call passes() and then
     * fails(), and an extra error added between the two (a uniqueness check a
     * controller performs itself) must not be wiped by a second run.
     */
    public function passes(): bool
    {
        if ($this->hasRun) {
            return $this->errors === [];
        }

        $this->hasRun = true;
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->value($field);
            $rules = explode('|', $ruleString);
            $isNullable = in_array('nullable', $rules, true);
            $isRequired = in_array('required', $rules, true);

            if (!$isRequired && !$isNullable && !$this->present($field)) {
                continue;
            }

            if ($isNullable && ($value === null || $value === '')) {
                $this->validated[$field] = null;

                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->applyRule($field, (string) $name, $parameter, $value)) {
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @throws ValidationException */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    private function value(string $field): mixed
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    private function present(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    private function applyRule(string $field, string $rule, ?string $parameter, mixed $value): bool
    {
        $ok = match ($rule) {
            'required'  => $value !== null && $value !== '' && $value !== [],
            'string'    => is_string($value),
            'integer'   => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'numeric'   => is_numeric($value),
            'boolean'   => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'yes', 'no', ''], true),
            'email'     => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'       => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date'      => is_string($value) && parse_date($value) !== false,
            'min'       => $this->compareSize($field, $value, (float) $parameter, '>='),
            'max'       => $this->compareSize($field, $value, (float) $parameter, '<='),
            'in'        => in_array((string) $value, explode(',', (string) $parameter), true),
            'not_in'    => !in_array((string) $value, explode(',', (string) $parameter), true),
            'regex'     => is_string($value) && preg_match((string) $parameter, $value) === 1,
            'slug'      => is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1,
            'phone'     => is_string($value) && preg_match('/^[0-9 +().\-]{6,30}$/', $value) === 1,
            'confirmed' => is_string($value) && $value === ($this->data[$field . '_confirmation'] ?? null),
            'same'      => $value === ($this->data[(string) $parameter] ?? null),
            'different' => $value !== ($this->data[(string) $parameter] ?? null),
            'array'     => is_array($value),
            'accepted'  => in_array($value, [true, 1, '1', 'on', 'yes'], true),
            'hex'       => is_string($value) && preg_match('/^#?[0-9a-fA-F]{3,8}$/', $value) === 1,
            'after'     => is_string($value) && parse_date($value) !== false
                            && parse_date($value) > strtotime($parameter === 'now' ? 'now' : (string) $parameter),
            default     => true,
        };

        if (!$ok) {
            $this->errors[$field] = $this->message($field, $rule, $parameter);
        }

        return $ok;
    }

    /**
     * Size comparison for min/max.
     *
     * A field also carrying `numeric` or `integer` compares by value, every
     * other field by length (or element count for arrays). Without that
     * distinction "5" would fail `numeric|min:2` for being one character long.
     */
    private function compareSize(string $field, mixed $value, float $limit, string $operator): bool
    {
        $rules = explode('|', $this->rules[$field] ?? '');
        $numeric = in_array('numeric', $rules, true) || in_array('integer', $rules, true);

        $size = match (true) {
            $numeric && is_numeric($value) => (float) $value,
            is_bool($value), is_int($value), is_float($value) => (float) $value,
            is_string($value)              => (float) mb_strlen($value),
            is_array($value)               => (float) count($value),
            default                        => 0.0,
        };

        return $operator === '>=' ? $size >= $limit : $size <= $limit;
    }

    private function message(string $field, string $rule, ?string $parameter): string
    {
        $key = $field . '.' . $rule;

        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        if (isset($this->messages[$field])) {
            return $this->messages[$field];
        }

        $label = $this->labels[$field] ?? str_replace('_', ' ', $field);

        return match ($rule) {
            'required'  => sprintf('Le champ %s est obligatoire.', $label),
            'email'     => sprintf('Le champ %s doit être une adresse e-mail valide.', $label),
            'url'       => sprintf('Le champ %s doit être une URL valide.', $label),
            'integer'   => sprintf('Le champ %s doit être un nombre entier.', $label),
            'numeric'   => sprintf('Le champ %s doit être numérique.', $label),
            'date'      => sprintf('Le champ %s doit être une date valide.', $label),
            'min'       => sprintf('Le champ %s doit contenir au moins %s caractères.', $label, (string) $parameter),
            'max'       => sprintf('Le champ %s ne peut pas dépasser %s caractères.', $label, (string) $parameter),
            'in'        => sprintf('La valeur du champ %s est invalide.', $label),
            'confirmed' => sprintf('La confirmation du champ %s ne correspond pas.', $label),
            'slug'      => sprintf('Le champ %s doit être un identifiant en minuscules (a-z, 0-9, tirets).', $label),
            'phone'     => sprintf('Le champ %s doit être un numéro de téléphone valide.', $label),
            'after'     => sprintf('Le champ %s doit être une date postérieure.', $label),
            'accepted'  => sprintf('Le champ %s doit être accepté.', $label),
            default     => sprintf('Le champ %s est invalide.', $label),
        };
    }

    /** Record an error a rule cannot express, such as a uniqueness check. */
    public function addError(string $field, string $message): void
    {
        $this->passes();
        $this->errors[$field] = $message;
        unset($this->validated[$field]);
    }
}
