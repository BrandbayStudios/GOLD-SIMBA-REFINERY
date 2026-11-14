<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
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
        // Hostinger / CDN in front of the app: trust forwarded proto so HTTPS URLs are generated.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->web(append: [EnsureAccountActive::class]);
        $middleware->api(append: [EnsureAccountActive::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $r) => $r->user()?->dashboardRoute() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
