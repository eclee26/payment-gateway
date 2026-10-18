<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyHmacSignature;
use App\Support\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // ThrottleRequests is hoisted by the priority list; keep signature
        // verification ahead of it so the API limiter can key by merchant.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: VerifyHmacSignature::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never flash raw card data back into the session on validation errors.
        $exceptions->dontFlash(['card.number', 'card.cvc', 'password', 'password_confirmation', 'current_password']);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Consistent {"error": {...}} envelope for every API failure.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiError::response(422, 'invalid_request_error', 'validation_failed', 'The given data was invalid.', [
                    'errors' => $e->errors(),
                ]),
                $e instanceof NotFoundHttpException => ApiError::response(404, 'invalid_request_error', 'resource_missing', 'No such resource.'),
                $e instanceof ThrottleRequestsException => ApiError::response(429, 'rate_limit_error', 'rate_limited', 'Too many requests.')
                    ->withHeaders($e->getHeaders()),
                $e instanceof HttpExceptionInterface => ApiError::response($e->getStatusCode(), 'invalid_request_error', 'http_error', $e->getStatusCode() < 500 ? $e->getMessage() : 'Server error.'),
                default => null,
            };
        });
    })->create();
