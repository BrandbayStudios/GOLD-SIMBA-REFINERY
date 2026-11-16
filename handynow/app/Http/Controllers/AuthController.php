<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesNrc;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use ValidatesNrc;

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
        $field = str_contains($data['login'], '@') ? 'email' : 'phone';
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

    /** Simple client account: name, phone, NRC and an address pinned on the map. Email is optional. */
    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))) ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'nrc_number' => $this->nrcRule(),
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'email' => 'nullable|email|max:120|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => 'accepted',
        ], [
            'latitude.required' => 'Tap the map to pin your address.',
            'longitude.required' => 'Tap the map to pin your address.',
            'terms.accepted' => 'Please accept the terms to continue.',
        ]);
        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }
        $nrc = $this->uniqueNrc($data['nrc_number']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $phone,
            'nrc_number' => $nrc,
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
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
