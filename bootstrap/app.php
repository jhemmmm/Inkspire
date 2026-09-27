<?php

use App\Http\Middleware\EnforceIdleSessionTimeout;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifySingleSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            VerifySingleSession::class,
            EnforceIdleSessionTimeout::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->trustProxies(at: '*');

        // PayMongo's servers POST here with no session/CSRF token available
        // (POS-03) — signature-verified instead, see routes/web.php.
        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($e instanceof InvalidSignatureException) {
                return Inertia::render('public/DesignReview', ['state' => 'expired'])->toResponse($request)->setStatusCode(403);
            }

            if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
                return Inertia::render('errors/Forbidden', [
                    'role' => $request->user()?->role?->value,
                    'dashboardHref' => $request->user()
                        ? route($request->user()->role->portalRoute())
                        : route('login'),
                ])->toResponse($request)->setStatusCode(403);
            }

            // Business-rule-conflict aborts (abort_unless/abort_if(..., 422, ...))
            // reach here as plain HttpExceptions. Inertia's client never sends
            // Accept: application/json, so expectsJson() is always false for
            // Inertia requests — without this branch, Laravel's default renderer
            // would return a raw HTML/debug error page, which Inertia's client
            // then displays as a full-viewport error overlay instead of handling
            // it gracefully. Redirect back with a flashed error toast instead,
            // matching the success-toast convention already used across the
            // controllers that throw these aborts.
            if ($response->getStatusCode() === 422 && $request->header(Header::INERTIA)) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

                return back();
            }

            return $response;
        });
    })->create();
