<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $profile = $user->providerProfile()->with('categories')->first();

        return view('provider.profile', ['user' => $user, 'profile' => $profile, 'categories' => Category::active()->get()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'business_name' => 'nullable|string|max:150',
            'city' => 'required|string|max:80',
            'bio' => 'nullable|string|max:1000',
            'years_experience' => 'nullable|integer|min:0|max:60',
            'categories' => 'required|array|min:1',
            'categories.*' => 'integer|exists:categories,id',
            'operating_areas' => 'required|string|max:500',
            'service_radius_km' => 'required|integer|min:1|max:200',
            'base_latitude' => 'nullable|numeric|between:-90,90',
            'base_longitude' => 'nullable|numeric|between:-180,180',
            'avatar' => 'nullable|image|max:2048',
        ]);
        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['phone' => 'This phone number is used by another account.']);
        }
        $userData = ['name' => $data['name'], 'email' => $data['email'], 'phone' => $phone, 'city' => $data['city']];
        if ($request->hasFile('avatar')) {
            $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $user->update($userData);
        $profile = $user->providerProfile;
        $profile->update([
            'business_name' => $data['business_name'] ?? null,
            'whatsapp' => ! empty($data['whatsapp']) ? zm_phone($data['whatsapp']) : $phone,
            'bio' => $data['bio'] ?? null,
            'years_experience' => $data['years_experience'] ?? 0,
            'operating_areas' => array_values(array_filter(array_map('trim', explode(',', $data['operating_areas'])))),
            'service_radius_km' => $data['service_radius_km'],
            'base_latitude' => $data['base_latitude'] ?? $profile->base_latitude,
            'base_longitude' => $data['base_longitude'] ?? $profile->base_longitude,
        ]);
        $profile->categories()->sync($data['categories']);

        return back()->with('success', 'Profile updated.');
    }

    public function documents(Request $request, Notifier $notify)
    {
        $data = $request->validate([
            'nrc_number' => ['required', 'string', 'max:30', 'regex:/^\d{6}\/\d{2}\/\d$/'],
            'business_reg_number' => 'nullable|string|max:50',
            'nrc_document' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'business_document' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], ['nrc_number.regex' => 'Enter your NRC number in the format 123456/78/1.']);
        $profile = $request->user()->providerProfile;
        $update = ['nrc_number' => $data['nrc_number'], 'business_reg_number' => $data['business_reg_number'] ?? null];
        foreach (['nrc_document', 'business_document'] as $f) {
            if ($request->hasFile($f)) {
                $update[$f] = $request->file($f)->store('provider-documents', 'local');
            }
        }
        // Re-submitting documents after a rejection puts the account back in the review queue.
        if ($profile->verification_status === 'rejected') {
            $update['verification_status'] = 'pending';
        }
        $profile->update($update);
        $notify->admins('Provider documents updated', $request->user()->name.' updated verification documents.', route('admin.providers.show', $request->user()), 'verification');

        return back()->with('success', 'Verification details updated.');
    }

    public function viewDocument(Request $request, string $type)
    {
        abort_unless(in_array($type, ['nrc_document', 'business_document'], true), 404);
        $path = $request->user()->providerProfile->{$type};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
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
