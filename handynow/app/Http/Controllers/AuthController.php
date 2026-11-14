<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:120',
            'password' => 'required|string',
        ]);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? $this->findPhone($data['login']) : mb_strtolower($data['login']);

        if (! $value || ! Auth::attempt([$field => $value, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'These details do not match our records.']);
        }
        if (Auth::user()->isSuspended()) {
            Auth::logout();

            return back()->withErrors(['login' => 'Your account has been suspended. Please contact HANDY NOW support.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->dashboardRoute());
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'city' => 'nullable|string|max:80',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => 'accepted',
        ]);
        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'city' => $data['city'] ?? null,
            'password' => $data['password'],
            'role' => User::ROLE_CUSTOMER,
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')->with('success', 'Welcome to HANDY NOW, '.$user->name.'!');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function findPhone(string $input): ?string
    {
        $p = zm_phone($input);

        return strlen($p) >= 9 ? $p : null;
    }
}
