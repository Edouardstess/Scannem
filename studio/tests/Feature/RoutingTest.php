<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RequiresGalleryDeleteMiddleware;
use App\Models\Role;
use Tests\Support\Factory;
use Tests\Support\TestCase;

final class RoutingTest extends TestCase
{
    public function testCleanUrlsResolveToControllers(): void
    {
        $router = new Router();
        $router->get('/gallery/{token}', static fn (Request $r, array $p) => \App\Core\Response::text($p['token']));

        $response = $router->dispatch($this->request('GET', '/gallery/abc123'));

        $this->assertSame('abc123', $response->body());
    }

    public function testConstrainedParametersAreEnforced(): void
    {
        $router = new Router();
        $router->get('/admin/clients/{id:\d+}', static fn (Request $r, array $p) => \App\Core\Response::text($p['id']));

        $this->assertSame('42', $router->dispatch($this->request('GET', '/admin/clients/42'))->body());

        $this->assertThrows(HttpException::class, function () use ($router): void {
            $router->dispatch($this->request('GET', '/admin/clients/not-a-number'));
        });
    }

    public function testUnknownPathsAre404AndWrongVerbsAre405(): void
    {
        $router = new Router();
        $router->get('/contact', static fn () => \App\Core\Response::text('ok'));

        try {
            $router->dispatch($this->request('GET', '/nowhere'));
            $this->fail('Expected a 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->statusCode());
        }

        try {
            $router->dispatch($this->request('DELETE', '/contact'));
            $this->fail('Expected a 405.');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->statusCode());
        }
    }

    public function testMiddlewareCanShortCircuitBeforeTheController(): void
    {
        $router = new Router();
        $reached = false;

        $router->get('/admin', static function () use (&$reached) {
            $reached = true;

            return \App\Core\Response::text('secret');
        }, [AuthMiddleware::class]);

        $response = $router->dispatch($this->request('GET', '/admin'));

        $this->assertFalse($reached, 'The controller must not run for an unauthenticated request.');
        $this->assertSame(302, $response->status());
    }

    public function testAuthMiddlewareLetsAnAuthenticatedUserThrough(): void
    {
        $user = Factory::user('router@example.test', 'correct-horse-battery');
        Auth::login($user, $this->request('GET', '/admin'));

        $middleware = new AuthMiddleware();

        $this->assertNull($middleware->handle($this->request('GET', '/admin'), []));
    }

    public function testAuthMiddlewareAnswersAjaxWithJson(): void
    {
        $request = new Request(
            'GET',
            '/admin/galleries',
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            [],
            []
        );

        $response = (new AuthMiddleware())->handle($request, []);

        $this->assertNotNull($response);
        $this->assertSame(401, $response->status());
        $this->assertStringContains('application/json', (string) $response->getHeader('Content-Type'));
    }

    public function testPermissionMiddlewareRefusesAnInsufficientRole(): void
    {
        $editor = Factory::user('editor2@example.test', 'correct-horse-battery', Role::EDITOR);
        Auth::login($editor, $this->request('GET', '/admin'));

        $response = (new RequiresGalleryDeleteMiddleware())->handle($this->request('GET', '/admin/galleries/1'), []);

        $this->assertNotNull($response, 'An editor must not reach a delete route.');
        $this->assertSame(403, $response->status());
    }

    public function testPermissionMiddlewareAllowsASufficientRole(): void
    {
        $admin = Factory::user('admin2@example.test', 'correct-horse-battery', Role::SUPER_ADMIN);
        Auth::login($admin, $this->request('GET', '/admin'));

        $this->assertNull(
            (new RequiresGalleryDeleteMiddleware())->handle($this->request('GET', '/admin/galleries/1'), [])
        );
    }

    public function testGuestMiddlewareKeepsAnAuthenticatedUserOffTheLoginPage(): void
    {
        $this->assertNull((new GuestMiddleware())->handle($this->request('GET', '/admin/login'), []));

        Auth::login(Factory::user('guest@example.test', 'correct-horse-battery'), $this->request('GET', '/admin/login'));

        $response = (new GuestMiddleware())->handle($this->request('GET', '/admin/login'), []);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
    }

    public function testEveryDeclaredRouteResolvesToACallableAction(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        $broken = [];
        $count = 0;

        foreach ($router->routes() as $method => $routes) {
            foreach ($routes as $route) {
                $count++;
                [$class, $action] = $route['handler'];

                if (!class_exists($class) || !method_exists($class, $action)) {
                    $broken[] = $method . ' ' . $route['pattern'] . ' → ' . $class . '::' . $action;
                }
            }
        }

        $this->assertGreaterThan(50, $count, 'The route table should not have shrunk unexpectedly.');
        $this->assertSame([], $broken, "Dead route:\n" . implode("\n", $broken));
    }

    public function testEveryRouteMiddlewareClassExists(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        $missing = [];

        foreach ($router->routes() as $routes) {
            foreach ($routes as $route) {
                foreach ($route['middleware'] as $middleware) {
                    if (!class_exists($middleware)) {
                        $missing[] = $middleware;
                    }
                }
            }
        }

        $this->assertSame([], array_unique($missing));
    }

    public function testAdminRoutesAreAllBehindAuthentication(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        // The login routes are the only /admin paths a stranger may reach.
        $public = ['/admin/login'];
        $unguarded = [];

        foreach ($router->routes() as $method => $routes) {
            foreach ($routes as $route) {
                if (!str_starts_with($route['pattern'], '/admin')) {
                    continue;
                }

                if (in_array($route['pattern'], $public, true)) {
                    continue;
                }

                if (!in_array(AuthMiddleware::class, $route['middleware'], true)) {
                    $unguarded[] = $method . ' ' . $route['pattern'];
                }
            }
        }

        $this->assertSame([], $unguarded, "Admin route without AuthMiddleware:\n" . implode("\n", $unguarded));
    }

    public function testClientRoutesCarryNoAdminGuard(): void
    {
        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';

        // Clients hold a token, not an account: putting AuthMiddleware on a
        // gallery route would lock every client out of their own photographs.
        $wrong = [];

        foreach ($router->routes() as $method => $routes) {
            foreach ($routes as $route) {
                $isClientRoute = str_starts_with($route['pattern'], '/gallery')
                    || str_starts_with($route['pattern'], '/download')
                    || str_starts_with($route['pattern'], '/media');

                if ($isClientRoute && in_array(AuthMiddleware::class, $route['middleware'], true)) {
                    $wrong[] = $method . ' ' . $route['pattern'];
                }
            }
        }

        $this->assertSame([], $wrong);
    }

    public function testNamedRoutesBuildPaths(): void
    {
        $router = new Router();
        $router->get('/admin/clients/{id:\d+}', static fn () => null, [], 'admin.clients.show');

        $this->assertSame('/admin/clients/7', $router->route('admin.clients.show', ['id' => 7]));
    }

    private function request(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], ['HTTP_USER_AGENT' => 'PHPUnit'], [], []);
    }
}
