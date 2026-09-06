<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'role' => 'customer',
        ]);

        $token = $user->createToken('eventhub-web')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function registerVendor(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'price_from' => ['nullable', 'numeric'],
            'price_unit' => ['nullable', 'string', 'max:60'],
            'facebook_url' => ['nullable', 'url'],
            'instagram_url' => ['nullable', 'url'],
            'tiktok_url' => ['nullable', 'url'],
            'website_url' => ['nullable', 'url'],
        ]);

        $user = User::create([
            'name' => $data['owner_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'role' => 'vendor',
        ]);

        $slug = Str::slug($data['business_name']);
        $base = $slug;
        $i = 1;
        while (VendorProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'category_id' => $data['category_id'],
            'business_name' => $data['business_name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? $data['phone'],
            'email' => $data['email'],
            'city' => $data['city'],
            'area' => $data['area'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'price_from' => $data['price_from'] ?? null,
            'price_unit' => $data['price_unit'] ?? 'per event',
            'facebook_url' => $data['facebook_url'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'tiktok_url' => $data['tiktok_url'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'status' => 'pending',
            'source' => 'platform',
        ]);

        $token = $user->createToken('eventhub-web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'vendor_profile' => $vendor,
            'token' => $token,
            'message' => 'Application submitted for admin approval.',
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $token = $user->createToken('eventhub-web')->plainTextToken;

        return response()->json([
            'user' => $user,
            'vendor_profile' => $user->vendorProfile,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'vendor_profile' => $user->isVendor() ? $user->vendorProfile : null,
        ]);
    }
}
