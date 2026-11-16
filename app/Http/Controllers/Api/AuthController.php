<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ValidatesNrc;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Token authentication for the future Android / iOS apps (Laravel Sanctum). */
class AuthController extends Controller
{
    use ValidatesNrc;

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))) ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:120|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'nrc_number' => $this->nrcRule(),
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'device_name' => 'nullable|string|max:100',
        ]);
        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This phone number is already registered.']);
        }
        $nrc = $this->uniqueNrc($data['nrc_number']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $phone, 'nrc_number' => $nrc, 'address' => $data['address'],
            'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'password' => $data['password'], 'role' => 'customer']);

        return response()->json(['token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken, 'user' => $this->user($user)], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string', 'device_name' => 'nullable|string|max:100']);
        $user = str_contains($data['login'], '@')
            ? User::where('email', mb_strtolower($data['login']))->first()
            : User::where('phone', zm_phone($data['login']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'These details do not match our records.']);
        }
        if ($user->isSuspended()) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        return response()->json(['token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken, 'user' => $this->user($user)]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->user($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    private function user(User $u): array
    {
        $p = $u->providerProfile;

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'address' => $u->address, 'latitude' => $u->latitude, 'longitude' => $u->longitude, 'role' => $u->role, 'status' => $u->status,
            'provider' => $p ? ['verification_status' => $p->verification_status, 'is_online' => $p->is_online, 'rating' => $p->rating_avg] : null,
        ];
    }
}
