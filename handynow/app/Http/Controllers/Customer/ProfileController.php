<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ValidatesNrc;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    use ValidatesNrc;

    public function edit(Request $request)
    {
        return view('customer.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))) ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'nrc_number' => $this->nrcRule(),
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'avatar' => 'nullable|image|max:2048',
        ], ['latitude.required' => 'Tap the map to pin your address.', 'longitude.required' => 'Tap the map to pin your address.']);
        $data['phone'] = zm_phone($data['phone']);
        if (User::where('phone', $data['phone'])->where('id', '!=', $user->id)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is used by another account.']);
        }
        $data['nrc_number'] = $this->uniqueNrc($data['nrc_number'], $user->id);
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password changed.');
    }
}
