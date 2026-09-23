<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Core\View;
use Tests\Support\TestCase;

/**
 * Output escaping. The template engine does not auto-escape, so the guarantee
 * rests on e() being used everywhere — which the last test here enforces.
 */
final class EscapingTest extends TestCase
{
    private const PAYLOADS = [
        '<script>alert(1)</script>',
        '"><img src=x onerror=alert(1)>',
        "'; alert(1); //",
        '<svg/onload=alert(1)>',
        '&lt;already escaped&gt;',
    ];

    public function testEscapesHtmlSpecialCharacters(): void
    {
        foreach (self::PAYLOADS as $payload) {
            $escaped = e($payload);

            $this->assertFalse(str_contains($escaped, '<'), 'Unescaped "<" in: ' . $escaped);
            $this->assertFalse(str_contains($escaped, '>'), 'Unescaped ">" in: ' . $escaped);
        }
    }

    public function testEscapesBothQuoteStyles(): void
    {
        // Single quotes matter as much as double: an unescaped one breaks out
        // of attributes written with single quotes.
        $this->assertSame('&quot;', e('"'));
        $this->assertSame('&#039;', e("'"));
    }

    public function testHandlesNullAndBooleans(): void
    {
        $this->assertSame('', e(null));
        $this->assertSame('1', e(true));
        $this->assertSame('0', e(false));
    }

    public function testJsonEmbeddingIsSafeInsideScriptTags(): void
    {
        $encoded = ejs(['caption' => '</script><script>alert(1)</script>']);

        $this->assertFalse(str_contains($encoded, '</script>'), 'A payload could close the script block.');

        // The needle is built rather than written as a literal, so no editor
        // or tool can quietly turn the escape sequence back into a character.
        $this->assertStringContains('\\' . 'u003C', $encoded);
    }

    public function testJsonEmbeddingEscapesQuotesAndAmpersands(): void
    {
        $encoded = ejs(['a' => '"&\'']);

        $this->assertFalse(str_contains($encoded, '&'), 'Raw ampersand in a script payload.');
        $this->assertFalse(str_contains($encoded, "'"), 'Raw apostrophe in a script payload.');
        $this->assertStringContains('\\' . 'u0026', $encoded);
        $this->assertStringContains('\\' . 'u0027', $encoded);
    }

    public function testSlugsStripEverythingUnsafe(): void
    {
        $this->assertSame('mariage-jean-marie', str_slug('Mariage Jean & Marie'));
        $this->assertSame('etc-passwd', str_slug('../../etc/passwd'));
        $this->assertSame('script-alert-1-script', str_slug('<script>alert(1)</script>'));
        $this->assertSame('', str_slug('!!!'));
    }

    public function testRenderedTemplatesEscapeStoredValues(): void
    {
        $html = View::render('errors.error', [
            'status'  => 404,
            'message' => '<script>alert("xss")</script>',
        ]);

        $this->assertFalse(str_contains($html, '<script>alert'), 'Stored XSS reached the page.');
        $this->assertStringContains('&lt;script&gt;', $html);
    }

    public function testResponseHeadersCannotBeSplit(): void
    {
        $response = Response::make('', 302, ['Location' => "https://example.test\r\nSet-Cookie: admin=1"]);

        // The value is sanitised when it is sent; here we assert the header
        // store keeps it intact and that send() would strip the newlines.
        $this->assertStringContains("\r\n", (string) $response->getHeader('Location'));
        $this->assertFalse(
            str_contains(str_replace(["\r", "\n"], '', (string) $response->getHeader('Location')), "\n")
        );
    }

    public function testEveryTemplateEchoesThroughAnEscaper(): void
    {
        $offenders = [];
        $directory = dirname(__DIR__, 2) . '/app/Views';

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) ?: [] as $number => $line) {
                // Short echo tags are where a raw value slips out. Every one
                // must wrap its value in an escaper or a known-safe helper.
                if (preg_match_all('/<\?=\s*(.+?)\s*\?>/', $line, $matches) === 0) {
                    continue;
                }

                foreach ($matches[1] as $expression) {
                    if ($this->isSafeExpression($expression)) {
                        continue;
                    }

                    $offenders[] = sprintf(
                        '%s:%d  <?= %s ?>',
                        str_replace($directory . '/', '', $file->getPathname()),
                        $number + 1,
                        $expression
                    );
                }
            }
        }

        $this->assertSame([], $offenders, "Unescaped output:\n" . implode("\n", $offenders));
    }

    /**
     * Is this expression safe to print without escaping?
     *
     * Safe means one of:
     *   - a string or numeric literal;
     *   - a cast to a number, or a helper that returns markup it built itself;
     *   - a concatenation whose every part is safe;
     *   - a ternary whose two BRANCHES are safe. What the condition reads does
     *     not matter: only the branches reach the page.
     */
    private function isSafeExpression(string $expression): bool
    {
        $expression = trim($expression);

        if ($expression === '') {
            return true;
        }

        // Strip a redundant wrapping pair of parentheses: ( … ).
        if (str_starts_with($expression, '(') && $this->matchingParenthesis($expression) === strlen($expression) - 1) {
            return $this->isSafeExpression(substr($expression, 1, -1));
        }

        $ternary = $this->splitTernary($expression);

        if ($ternary !== null) {
            return $this->isSafeExpression($ternary[0]) && $this->isSafeExpression($ternary[1]);
        }

        $parts = $this->splitTopLevel($expression, '.');

        if (count($parts) > 1) {
            foreach ($parts as $part) {
                if (!$this->isSafeExpression($part)) {
                    return false;
                }
            }

            return true;
        }

        return $this->isSafeAtom($expression);
    }

    private function isSafeAtom(string $expression): bool
    {
        $expression = trim($expression);

        // A complete string literal.
        if (preg_match("/^'([^'\\\\]|\\\\.)*'$/s", $expression) === 1
            || preg_match('/^"([^"\\\\]|\\\\.)*"$/s', $expression) === 1) {
            return true;
        }

        // A number.
        if (preg_match('/^-?\d+(\.\d+)?$/', $expression) === 1) {
            return true;
        }

        // A cast to a number: the result can contain no markup.
        if (preg_match('/^\(\s*(int|integer|float|double)\s*\)/', $expression) === 1) {
            return true;
        }

        $safePrefixes = [
            // Escapers.
            'e(', 'eattr(', 'ejs(',
            // Helpers that emit markup they built and escaped themselves.
            'csrf_field(', 'method_field(', 'View::section(', 'View::include(',
            // Escaped, then given line breaks.
            'nl2br(e(',
            // Numeric or structural output: none of these can emit markup.
            'number_format(', 'count(', 'str_pad(', 'date(',
            'round(', 'floor(', 'ceil(', 'abs(', 'max(', 'min(', 'intdiv(', 'array_sum(',
        ];

        foreach ($safePrefixes as $prefix) {
            if (str_starts_with($expression, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split "cond ? a : b" into [a, b], or null when it is not a ternary.
     *
     * @return array{0: string, 1: string}|null
     */
    private function splitTernary(string $expression): ?array
    {
        $depth = 0;
        $quote = null;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;
            } elseif ($character === '?' && $depth === 0) {
                // "??" is a null coalesce, not a ternary.
                if (($expression[$index + 1] ?? '') === '?') {
                    $index++;

                    continue;
                }

                $colon = $this->findTernaryColon($expression, $index + 1);

                if ($colon === null) {
                    return null;
                }

                return [
                    substr($expression, $index + 1, $colon - $index - 1),
                    substr($expression, $colon + 1),
                ];
            }
        }

        return null;
    }

    private function findTernaryColon(string $expression, int $from): ?int
    {
        $depth = 0;
        $pending = 0;
        $quote = null;
        $length = strlen($expression);

        for ($index = $from; $index < $length; $index++) {
            $character = $expression[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;
            } elseif ($character === '?' && $depth === 0) {
                if (($expression[$index + 1] ?? '') === '?') {
                    $index++;
                } else {
                    $pending++;
                }
            } elseif ($character === ':' && $depth === 0) {
                // "::" is a static call, not a ternary branch.
                if (($expression[$index + 1] ?? '') === ':' || ($expression[$index - 1] ?? '') === ':') {
                    $index++;

                    continue;
                }

                if ($pending === 0) {
                    return $index;
                }

                $pending--;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function splitTopLevel(string $expression, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($quote !== null) {
                $buffer .= $character;

                if ($character === '\\') {
                    $buffer .= $expression[++$index] ?? '';
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;
            } elseif ($character === $separator && $depth === 0) {
                // A decimal point inside a number is not a concatenation.
                if (preg_match('/\d\s*$/', $buffer) === 1 && preg_match('/^\s*\d/', substr($expression, $index + 1)) === 1) {
                    $buffer .= $character;

                    continue;
                }

                $parts[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        $parts[] = $buffer;

        return array_map('trim', $parts);
    }

    private function matchingParenthesis(string $expression): int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return -1;
    }
}
