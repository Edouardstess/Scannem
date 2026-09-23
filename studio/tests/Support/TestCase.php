<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Minimal xUnit-style base class.
 *
 * The project has no runtime dependencies on purpose (shared hosting without
 * Composer), and the test suite keeps that property: `php tests/run.php` works
 * on a bare PHP installation.
 */
abstract class TestCase
{
    private int $assertions = 0;

    /** @var array<int, string> */
    private array $failures = [];

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    /** @return array<int, string> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function resetResults(): void
    {
        $this->assertions = 0;
        $this->failures = [];
    }

    protected function pass(): void
    {
        $this->assertions++;
    }

    protected function fail(string $message): void
    {
        $this->assertions++;
        $this->failures[] = $message;
    }

    protected function assertTrue(mixed $value, string $message = ''): void
    {
        $value === true
            ? $this->pass()
            : $this->fail($message !== '' ? $message : 'Expected true, got ' . $this->describe($value));
    }

    protected function assertFalse(mixed $value, string $message = ''): void
    {
        $value === false
            ? $this->pass()
            : $this->fail($message !== '' ? $message : 'Expected false, got ' . $this->describe($value));
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $expected === $actual
            ? $this->pass()
            : $this->fail($message !== '' ? $message : sprintf(
                'Expected %s, got %s',
                $this->describe($expected),
                $this->describe($actual)
            ));
    }

    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $expected !== $actual
            ? $this->pass()
            : $this->fail($message !== '' ? $message : 'Expected a different value than ' . $this->describe($expected));
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $value === null
            ? $this->pass()
            : $this->fail($message !== '' ? $message : 'Expected null, got ' . $this->describe($value));
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $value !== null
            ? $this->pass()
            : $this->fail($message !== '' ? $message : 'Expected a value, got null');
    }

    protected function assertCount(int $expected, array $actual, string $message = ''): void
    {
        count($actual) === $expected
            ? $this->pass()
            : $this->fail($message !== '' ? $message : sprintf(
                'Expected %d items, got %d',
                $expected,
                count($actual)
            ));
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        in_array($needle, $haystack, true)
            ? $this->pass()
            : $this->fail($message !== '' ? $message : $this->describe($needle) . ' not found in the array');
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        str_contains($haystack, $needle)
            ? $this->pass()
            : $this->fail($message !== '' ? $message : sprintf('"%s" not found in the output', $needle));
    }

    protected function assertGreaterThan(int|float $limit, int|float $actual, string $message = ''): void
    {
        $actual > $limit
            ? $this->pass()
            : $this->fail($message !== '' ? $message : sprintf('Expected more than %s, got %s', $limit, $actual));
    }

    /** @param callable():void $callback */
    protected function assertThrows(string $expectedClass, callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $e instanceof $expectedClass
                ? $this->pass()
                : $this->fail(sprintf('Expected %s, got %s: %s', $expectedClass, $e::class, $e->getMessage()));

            return;
        }

        $this->fail($message !== '' ? $message : 'Expected ' . $expectedClass . ', nothing was thrown');
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            is_bool($value)   => $value ? 'true' : 'false',
            is_null($value)   => 'null',
            is_array($value)  => 'array(' . count($value) . ')',
            is_object($value) => $value::class,
            is_string($value) => '"' . (strlen($value) > 60 ? substr($value, 0, 57) . '…' : $value) . '"',
            default           => (string) $value,
        };
    }
}
