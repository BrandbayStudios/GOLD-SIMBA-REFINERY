<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'role'     => 'customer',
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->userResponse($request, 'Welcome to EventHub, '.$user->name.'!');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            return response()->json([
                'ok'      => false,
                'message' => 'Those credentials do not match our records.',
            ], 422);
        }

        $request->session()->regenerate();

        return $this->userResponse($request, 'Signed in successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Signed out.',
                'csrf'    => csrf_token(),
            ]);
        }

        return redirect()->route('home');
    }

    private function userResponse(Request $request, string $message)
    {
        $user = Auth::user();

        return response()->json([
            'ok'      => true,
            'message' => $message,
            // Session regeneration rotates the CSRF token; hand the fresh one back
            // so subsequent AJAX requests from the page stay valid.
            'csrf'    => csrf_token(),
            'user'    => [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }
}
