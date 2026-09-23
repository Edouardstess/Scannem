<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Services\SettingsService;
use Throwable;

/**
 * Application kernel: boots configuration, wires the router, renders errors.
 */
final class Application
{
    private static ?self $instance = null;

    private Router $router;

    private function __construct(private string $basePath)
    {
        $this->router = new Router();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $app = new self(rtrim($basePath, '/'));
        self::$instance = $app;

        Config::loadEnv($app->basePath . '/.env');
        Config::load($app->basePath . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');

        $app->configureErrorReporting();

        View::setBasePath($app->basePath . '/app/Views');

        return $app;
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('Application has not been booted.');
        }

        return self::$instance;
    }

    private function configureErrorReporting(): void
    {
        $debug = (bool) Config::get('app.debug');

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            // Turning notices into exceptions surfaces bugs during
            // development instead of letting them corrupt data quietly.
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal error', [
                    'message' => $error['message'],
                    'file'    => $error['file'],
                    'line'    => $error['line'],
                ]);
            }
        });
    }

    public function basePath(string $relative = ''): string
    {
        return $this->basePath . ($relative === '' ? '' : '/' . ltrim($relative, '/'));
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function loadRoutes(string $file): void
    {
        $router = $this->router;

        require $file;
    }

    /** Run the request through the router and return the response. */
    public function handle(Request $request): Response
    {
        try {
            $this->shareViewData($request);

            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return $this->renderHttpException($e, $request);
        } catch (Throwable $e) {
            return $this->renderThrowable($e, $request);
        }
    }

    /**
     * Values every template can rely on.
     *
     * Flash messages and old input are pulled once per request: reading them
     * inside a template would make their consumption depend on render order.
     */
    private function shareViewData(Request $request): void
    {
        View::share('request', $request);
        View::share('currentPath', $request->path());
        View::share('flashes', Session::pullFlashes());
        View::share('errors', Session::pullErrors());
        View::share('old', Session::pullOldInput());
        View::share('auth', Auth::user());
        View::share('appName', (string) Config::get('app.name'));

        // Branding (studio name, colours, social links) is editable from the
        // admin, so templates read it from settings rather than from config.
        View::share('settings', SettingsService::make()->all());
    }

    private function renderHttpException(HttpException $e, Request $request): Response
    {
        $status = $e->statusCode();

        if ($request->isAjax()) {
            return Response::json(['error' => $e->getMessage() ?: 'Erreur'], $status)
                ->withHeaders($e->headers());
        }

        $template = View::exists('errors.' . $status) ? 'errors.' . $status : 'errors.error';

        return Response::html(
            View::render($template, [
                'status'  => $status,
                'message' => $e->getMessage(),
            ]),
            $status
        )->withHeaders($e->headers());
    }

    private function renderThrowable(Throwable $e, Request $request): Response
    {
        Logger::error('Unhandled exception', [
            'type'    => $e::class,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'path'    => $request->path(),
        ]);

        $debug = (bool) Config::get('app.debug');

        if ($request->isAjax()) {
            return Response::json([
                'error' => $debug ? $e->getMessage() : 'Une erreur interne est survenue.',
            ], 500);
        }

        return Response::html(
            View::render('errors.500', [
                'status'    => 500,
                'message'   => $debug ? $e->getMessage() : '',
                'exception' => $debug ? $e : null,
            ]),
            500
        );
    }

    public function run(): void
    {
        $request = Request::capture();
        Session::start();

        $this->handle($request)->send();
    }
}
