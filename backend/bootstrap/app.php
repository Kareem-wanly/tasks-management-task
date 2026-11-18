<?php

use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bitrix POSTs here when the app is opened; the token is verified
        // with Bitrix instead of a CSRF token.
        $middleware->validateCsrfTokens(except: ['auth/bitrix/callback']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'This action is unauthorized.',
                ], 403);
            }
        });

        // Thrown by services when a valid request breaks a business rule.
        $exceptions->render(fn (BusinessRuleException $e) => response()->json($e->body(), $e->status));

        // A change Bitrix24 didn't accept was not saved here either; say why.
        $exceptions->render(function (BitrixException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            [$status, $message] = match (true) {
                $e->isMissingToken() => [403, 'Sign in with Bitrix24 to change data that is synced with Bitrix24.'],
                $e->errorCode === BitrixException::UNREACHABLE => [503, 'Bitrix24 is not responding. Your change was not saved; try again in a moment.'],
                $e->errorCode === BitrixException::CANNOT_SYNC => [422, $e->getMessage()],
                $e->isAccessDenied() => [403, 'Bitrix24 does not allow you to do this.'],
                default => [422, 'Bitrix24 rejected the change: '.$e->getMessage()],
            };

            return response()->json(['message' => $message], $status);
        });

    })->create();
