<?php

use App\Http\Middleware\AuthenticateConnectorKey;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'connector.key' => AuthenticateConnectorKey::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('v1/*')) {
                return false;
            }

            return response()->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => $e->validator->errors()->first() ?: $e->getMessage(),
                ],
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('v1/*')) {
                return false;
            }

            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The requested resource was not found.',
                ],
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('v1/*')) {
                return false;
            }

            return response()->json([
                'error' => [
                    'code' => 'rate_limited',
                    'message' => 'Too many requests.',
                ],
            ], 429);
        });
    })->create();
