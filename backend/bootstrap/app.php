<?php

declare(strict_types=1);

use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\BusinessException;
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

        // A broken business rule (a wrong password, a role that can't be renamed...) is an
        // answer, not a fault: not logged. Bitrix24 failures still are (outages, refusals).
        $exceptions->dontReportWhen(fn (Throwable $e): bool => $e instanceof BusinessException && ! $e instanceof BitrixException);

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

        // A valid request that broke a business rule, or a change Bitrix24 didn't accept
        // (not saved here either): each exception carries its own status and message (ARC-7).
        $exceptions->render(function (BusinessException $e, Request $request) {
            // On a web page (the OAuth callback) a Bitrix failure keeps Laravel's error page.
            if ($e instanceof BitrixException && ! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json($e->body(), $e->status());
        });

    })->create();
