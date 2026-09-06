<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404, 'No vendor profile found for this account.');

        return response()->json([
            'profile_views' => $vendor->view_count,
            'total_enquiries' => $vendor->enquiries()->count(),
            'new_enquiries' => $vendor->enquiries()->where('status', 'new')->count(),
            'bookings' => $vendor->bookings()->count(),
            'confirmed_bookings' => $vendor->bookings()->where('status', 'confirmed')->count(),
            'average_rating' => (float) $vendor->rating_avg,
            'review_count' => $vendor->rating_count,
            'favourited_count' => $vendor->favouritedBy()->count(),
            'response_time_minutes' => $vendor->response_time_minutes,
            'status' => $vendor->status,
        ]);
    }

    public function profile(Request $request)
    {
        $vendor = $request->user()->vendorProfile()->with(['category', 'portfolioMedia', 'packages'])->firstOrFail();

        return new VendorProfileResource($vendor);
    }

    public function updateProfile(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'logo_path' => ['nullable', 'string'],
            'cover_path' => ['nullable', 'string'],
            'facebook_url' => ['nullable', 'url'],
            'instagram_url' => ['nullable', 'url'],
            'tiktok_url' => ['nullable', 'url'],
            'website_url' => ['nullable', 'url'],
            'price_from' => ['nullable', 'numeric'],
            'price_unit' => ['nullable', 'string', 'max:60'],
        ]);

        $vendor->update($data);

        return new VendorProfileResource($vendor->fresh(['category']));
    }
}
