<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\ApiError;

// Exact-match router with per-route guards.
//   API routes (/api/...) return JSON; errors become {"success":false,...}.
//   Page routes render views; guards redirect to the right login page.
final class Router
{
    /** @var array<string, array{0: callable, 1: ?string}> */
    private array $routes = [];

    public function get(string $path, callable $handler, ?string $guard = null): void  { $this->add('GET', $path, $handler, $guard); }
    public function post(string $path, callable $handler, ?string $guard = null): void { $this->add('POST', $path, $handler, $guard); }

    private function add(string $method, string $path, callable $handler, ?string $guard): void
    {
        $this->routes["$method $path"] = [$handler, $guard];
    }

    public function dispatch(Request $request): void
    {
        $isApi = str_starts_with($request->path, '/api/');
        try {
            $route = $this->routes["{$request->method} {$request->path}"] ?? null;
            if ($route === null) {
                $otherMethod = $request->method === 'GET' ? 'POST' : 'GET';
                if (isset($this->routes["$otherMethod {$request->path}"])) {
                    throw new ApiError(405, 'METHOD_NOT_ALLOWED', 'Method not allowed');
                }
                throw ApiError::notFound();
            }
            [$handler, $guard] = $route;
            if ($guard !== null) $this->guard($guard, $isApi);

            $result = $handler($request);
            if ($isApi) $this->json(['success' => true] + ($result ?? []));
        } catch (ApiError $e) {
            if (!$isApi) {
                http_response_code($e->status);
                echo $e->status === 404 ? '404 Not Found' : e($e->getMessage());
                return;
            }
            $this->json(['success' => false, 'code' => $e->errorCode, 'message' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            error_log((string) $e);
            if (!$isApi) {
                http_response_code(500);
                echo 'Something went wrong.';
                return;
            }
            $body = ['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'Something went wrong'];
            if (config('debug')) $body['debug'] = $e->getMessage();
            $this->json($body, 500);
        }
    }

    // Guards: 'player' / 'admin'. API routes get 401, pages redirect to login.
    private function guard(string $guard, bool $isApi): void
    {
        $ok = match ($guard) {
            'player' => Auth::playerId() !== null,
            'admin'  => Auth::adminId() !== null,
        };
        if ($ok) return;
        if ($isApi) throw ApiError::unauthorized();
        redirect($guard === 'admin' ? 'admin' : 'user');
    }

    private function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
