<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/dev-login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*');

        $error = fn (int $status, string $code, string $message) => response()->json(
            ['code' => $code, 'message' => $message],
            $status,
        );

        // Every API error uses { code, message } (PRD §8).
        $exceptions->render(function (ApiException $e, Request $request) use ($isApi, $error) {
            return $isApi($request)
                ? $error($e->status, $e->errorCode, $e->getMessage())
                : response($e->getMessage(), $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi, $error) {
            if ($isApi($request)) {
                return $error(401, 'UNAUTHORIZED', 'Token tidak ada atau tidak sah');
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi, $error) {
            if ($isApi($request)) {
                $fields = implode(', ', array_keys($e->errors()));

                return $error(400, 'VALIDATION_ERROR', "Data tidak valid: {$fields}");
            }
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) use ($isApi, $error) {
            if ($isApi($request)) {
                $message = $e->getMessage() && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : 'Akses ditolak';

                return $error(403, 'FORBIDDEN', $message);
            }
        });

        // Route-model binding misses become e.g. MEETING_NOT_FOUND, ACTION_ITEM_NOT_FOUND.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi, $error) {
            if (! $isApi($request)) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $name = Str::of(class_basename($previous->getModel()))->snake()->upper();

                return $error(404, "{$name}_NOT_FOUND", 'Data tidak ditemukan');
            }

            return $error(404, 'NOT_FOUND', 'Endpoint tidak ditemukan');
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi, $error) {
            if ($isApi($request) && $e->getStatusCode() === 405) {
                return $error(405, 'METHOD_NOT_ALLOWED', 'Metode HTTP tidak didukung');
            }
        });
    })->create();
