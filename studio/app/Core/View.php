<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ViewNotFoundException;

/**
 * Plain-PHP template renderer with layout inheritance.
 *
 * No template compiler: on shared hosting a cache directory is one more thing
 * to get wrong, and PHP is already a template engine. Escaping is explicit via
 * the e() helper, which is the only safe default when the engine does not
 * auto-escape.
 */
final class View
{
    private static string $basePath = '';

    /** @var array<string, mixed> Values available to every template. */
    private static array $shared = [];

    private static ?string $layout = null;

    /** @var array<string, string> */
    private static array $sections = [];

    /** @var array<int, string> */
    private static array $sectionStack = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public static function shared(): array
    {
        return self::$shared;
    }

    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $data
     * @throws ViewNotFoundException
     */
    public static function render(string $template, array $data = []): string
    {
        $file = self::resolve($template);

        // A nested render must not clobber the outer render's layout state.
        $previousLayout = self::$layout;
        $previousSections = self::$sections;
        self::$layout = null;
        self::$sections = [];

        $content = self::evaluate($file, $data);

        $layout = self::$layout;
        $sections = self::$sections;

        self::$layout = $previousLayout;
        self::$sections = $previousSections;

        if ($layout === null) {
            return $content;
        }

        $sections['content'] = $sections['content'] ?? $content;

        return self::renderLayout($layout, $data, $sections);
    }

    /** @param array<string, mixed> $data @param array<string, string> $sections */
    private static function renderLayout(string $layout, array $data, array $sections): string
    {
        $previousSections = self::$sections;
        $previousLayout = self::$layout;

        self::$sections = $sections;
        self::$layout = null;

        $output = self::evaluate(self::resolve($layout), $data);

        $parent = self::$layout;
        $mergedSections = self::$sections;

        self::$sections = $previousSections;
        self::$layout = $previousLayout;

        if ($parent !== null) {
            $mergedSections['content'] = $output;

            return self::renderLayout($parent, $data, $mergedSections);
        }

        return $output;
    }

    /** @param array<string, mixed> $data */
    private static function evaluate(string $file, array $data): string
    {
        $scope = array_merge(self::$shared, $data);

        $render = static function (string $__file, array $__scope): string {
            extract($__scope, EXTR_SKIP);
            ob_start();

            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $scope);
    }

    private static function resolve(string $template): string
    {
        // Templates are developer-supplied, but the traversal guard costs
        // nothing and stops a future refactor from turning a route parameter
        // into a file path.
        $relative = str_replace(['..', "\0"], '', $template);
        $relative = str_replace('.', '/', $relative);
        $file = self::$basePath . '/' . ltrim($relative, '/') . '.php';

        if (!is_file($file)) {
            throw new ViewNotFoundException('View not found: ' . $template);
        }

        return $file;
    }

    public static function exists(string $template): bool
    {
        try {
            self::resolve($template);

            return true;
        } catch (ViewNotFoundException) {
            return false;
        }
    }

    // --- Template-side API -------------------------------------------------

    public static function extend(string $layout): void
    {
        self::$layout = $layout;
    }

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$sectionStack);

        if ($name === null) {
            return;
        }

        self::$sections[$name] = (string) ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    /** @param array<string, mixed> $data */
    public static function include(string $template, array $data = []): string
    {
        return self::evaluate(self::resolve($template), $data);
    }
}
