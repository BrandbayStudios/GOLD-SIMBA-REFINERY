<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out suspended accounts on their next request. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isSuspended()) {
            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => 'Your account has been suspended. Contact support.'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been suspended. Please contact HANDY NOW support.']);
        }

        return $next($request);
    }
}
