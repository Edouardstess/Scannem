<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\AuthorizationException;
use App\Exceptions\HttpException;

/**
 * Shared controller behaviour: rendering, redirects, validation, authorisation.
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::render($template, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(str_starts_with($path, 'http') ? $path : url($path), $status);
    }

    /** Redirect back to the referring page, falling back to a known path. */
    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->referer();
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
        $refererHost = $referer === '' ? '' : (string) (parse_url($referer, PHP_URL_HOST) ?: '');

        // Never redirect to a host we do not control: an open redirect turns
        // this application into a phishing springboard.
        if ($referer !== '' && ($refererHost === '' || $refererHost === $host)) {
            return Response::redirect($referer);
        }

        return $this->redirect($fallback);
    }


    /**
     * Re-render a form after a validation failure.
     *
     * @param array<string, string> $errors
     */
    protected function redirectWithErrors(Request $request, array $errors, string $fallback = '/'): Response
    {
        Session::flashErrors($errors);
        Session::flashInput($request->all());

        return $this->back($request, $fallback);
    }

    protected function authorize(string $permission): void
    {
        if (Auth::cannot($permission)) {
            throw new AuthorizationException();
        }
    }

    /** @throws HttpException */
    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array<string, mixed>
     * @throws HttpException
     */
    protected function orFail(?array $record, string $message = 'Introuvable.'): array
    {
        if ($record === null) {
            throw new HttpException(404, $message);
        }

        return $record;
    }

    protected function flashSuccess(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function flashError(string $message): void
    {
        Session::flash('error', $message);
    }

    protected function flashInfo(string $message): void
    {
        Session::flash('info', $message);
    }

    /** Current page number from ?page=, clamped to sane bounds. */
    protected function page(Request $request): int
    {
        return max(1, min(100000, $request->int('page', 1)));
    }

    /** @return array<string, mixed> */
    protected function paginationMeta(int $total, int $page, int $perPage): array
    {
        $pages = max(1, (int) ceil($total / max(1, $perPage)));

        return [
            'total'    => $total,
            'page'     => min($page, $pages),
            'per_page' => $perPage,
            'pages'    => $pages,
            'from'     => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to'       => min($total, $page * $perPage),
        ];
    }
}
