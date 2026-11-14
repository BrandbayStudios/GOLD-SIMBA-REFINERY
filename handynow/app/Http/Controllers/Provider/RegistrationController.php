<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('provider.register', ['categories' => Category::active()->get()]);
    }

    public function store(Request $request, Notifier $notify)
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'provider_type' => 'required|in:individual,business',
            'business_name' => 'nullable|required_if:provider_type,business|string|max:150',
            'nrc_number' => ['required', 'string', 'max:30', 'regex:/^\d{6}\/\d{2}\/\d$/'],
            'business_reg_number' => 'nullable|required_if:provider_type,business|string|max:50',
            'nrc_document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'business_document' => 'nullable|required_if:provider_type,business|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'categories' => 'required|array|min:1',
            'categories.*' => 'integer|exists:categories,id',
            'city' => 'required|string|max:80',
            'operating_areas' => 'required|string|max:500',
            'service_radius_km' => 'required|integer|min:1|max:200',
            'years_experience' => 'nullable|integer|min:0|max:60',
            'bio' => 'nullable|string|max:1000',
            'base_latitude' => 'nullable|numeric|between:-90,90',
            'base_longitude' => 'nullable|numeric|between:-180,180',
            'terms' => 'accepted',
        ], [
            'nrc_number.regex' => 'Enter your NRC number in the format 123456/78/1.',
            'business_name.required_if' => 'Business name is required for registered businesses.',
            'business_reg_number.required_if' => 'PACRA registration number is required for registered businesses.',
            'business_document.required_if' => 'Upload your business registration certificate.',
        ]);

        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }

        $user = DB::transaction(function () use ($request, $data, $phone) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $phone,
                'city' => $data['city'],
                'password' => $data['password'],
                'role' => User::ROLE_PROVIDER,
            ]);
            $profile = ProviderProfile::create([
                'user_id' => $user->id,
                'provider_type' => $data['provider_type'],
                'business_name' => $data['business_name'] ?? null,
                'nrc_number' => $data['nrc_number'],
                'business_reg_number' => $data['business_reg_number'] ?? null,
                // Identity documents go to the private disk, never the public web root.
                'nrc_document' => $request->file('nrc_document')->store('provider-documents', 'local'),
                'business_document' => $request->file('business_document')?->store('provider-documents', 'local'),
                'whatsapp' => isset($data['whatsapp']) ? zm_phone($data['whatsapp']) : $phone,
                'operating_areas' => array_values(array_filter(array_map('trim', explode(',', $data['operating_areas'])))),
                'service_radius_km' => $data['service_radius_km'],
                'years_experience' => $data['years_experience'] ?? 0,
                'bio' => $data['bio'] ?? null,
                'base_latitude' => $data['base_latitude'] ?? null,
                'base_longitude' => $data['base_longitude'] ?? null,
                'verification_status' => 'pending',
            ]);
            $profile->categories()->sync($data['categories']);

            return $user;
        });

        $notify->admins('Provider awaiting verification', "{$user->name} registered as a service provider.", route('admin.providers.show', $user), 'verification');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('provider.dashboard')->with('success', 'Registration received! Our team will verify your documents, usually within 24 hours.');
    }
}
