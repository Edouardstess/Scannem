<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Validator;

/**
 * Base class for form validation.
 *
 * Validation rules live here rather than inside controllers, for three
 * reasons: the same form is validated identically from HTTP and from the
 * command line, the rules for one screen are readable in one place, and a
 * controller that forgets to validate is visible as a controller that never
 * names a FormRequest.
 *
 * A subclass declares its rules; anything a rule cannot express — uniqueness,
 * a cross-field constraint, a lookup — goes in after().
 */
abstract class FormRequest
{
    protected Validator $validator;

    /** @var array<string, mixed> */
    protected array $input = [];

    protected Request $request;

    /** @return array<string, string> field => rule string */
    abstract protected function rules(): array;

    /** @return array<string, string> Human labels used in error messages. */
    protected function labels(): array
    {
        return [];
    }

    /** @return array<string, string> Overrides for specific rule messages. */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Checks the rule strings cannot express.
     *
     * Runs only once the rules have passed, so it can rely on the shape of
     * the data being correct.
     */
    protected function after(Validator $validator, Request $request): void
    {
    }

    /**
     * Values to store, derived from the validated input.
     *
     * Subclasses override this to normalise: trim, lowercase an address,
     * resolve a foreign key, cast a checkbox.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    protected function transform(array $validated, Request $request): array
    {
        return $validated;
    }

    public function validate(Request $request): self
    {
        $this->request = $request;
        $this->validator = Validator::make(
            $request->all(),
            $this->rules(),
            $this->messages(),
            $this->labels()
        );

        if ($this->validator->passes()) {
            $this->after($this->validator, $request);
        }

        $this->input = $this->validator->fails()
            ? []
            : $this->transform($this->validator->validated(), $request);

        return $this;
    }

    public function fails(): bool
    {
        return $this->validator->fails();
    }

    public function passes(): bool
    {
        return $this->validator->passes();
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->validator->errors();
    }

    /** @return array<string, mixed> The values to hand to a service. */
    public function data(): array
    {
        return $this->input;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    /** Null for a blank optional field, so the column stays NULL. */
    protected static function nullIfBlank(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    protected static function normaliseEmail(mixed $value): ?string
    {
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return $value === '' ? null : $value;
    }

    /** Y-m-d, or null when the field is empty or unparseable. */
    protected static function normaliseDate(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '') {
            return null;
        }

        $timestamp = parse_date($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
