<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class FeaturedController extends Controller
{
    public function index()
    {
        return VendorProfileResource::collection(
            VendorProfile::with('category')->where('is_featured', true)->latest('featured_until')->get()
        );
    }

    public function store(Request $request, VendorProfile $vendor)
    {
        $data = $request->validate(['featured_until' => ['required', 'date', 'after:today']]);

        $vendor->update(['is_featured' => true, 'featured_until' => $data['featured_until']]);

        return new VendorProfileResource($vendor->fresh('category'));
    }

    public function destroy(VendorProfile $vendor)
    {
        $vendor->update(['is_featured' => false, 'featured_until' => null]);

        return response()->json(['message' => 'Vendor unfeatured.']);
    }
}
