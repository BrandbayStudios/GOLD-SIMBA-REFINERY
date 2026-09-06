<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorProfile::with('category');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($category = $request->string('category')->toString()) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category));
        }

        if ($q = $request->string('q')->toString()) {
            $query->where('business_name', 'like', "%{$q}%");
        }

        return VendorProfileResource::collection($query->latest()->paginate((int) $request->input('per_page', 15)));
    }

    public function show(VendorProfile $vendor)
    {
        return new VendorProfileResource($vendor->load(['category', 'portfolioMedia', 'packages', 'reviews.user']));
    }

    public function updateStatus(Request $request, VendorProfile $vendor)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected']]);
        $vendor->update($data);

        return new VendorProfileResource($vendor->fresh('category'));
    }

    public function destroy(VendorProfile $vendor)
    {
        $vendor->delete();

        return response()->json(['message' => 'Vendor removed.']);
    }
}
