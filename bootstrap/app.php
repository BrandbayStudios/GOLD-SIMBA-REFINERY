<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
use Illuminate\Database\QueryException;
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
        // Fresh upload with no tables yet: show a setup notice instead of a 500 error.
        $exceptions->render(function (QueryException $e, Request $request) {
            $missing = str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist");
            if ($missing && ! file_exists(storage_path('app/installed.lock')) && ! $request->is('install')) {
                return response('<!DOCTYPE html><meta name="viewport" content="width=device-width"><title>HANDY NOW</title>'
                    .'<div style="font-family:system-ui;max-width:520px;margin:15vh auto;padding:24px;text-align:center;color:#101D35">'
                    .'<h1 style="color:#FF8A24">HANDY NOW</h1><p>The site is being set up. Please check back shortly.</p>'
                    .'<p style="color:#7487b0;font-size:13px">Administrator: open /install?token=… to finish installation.</p></div>', 503);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
