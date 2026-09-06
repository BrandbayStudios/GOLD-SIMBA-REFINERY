<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\Favourite;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class FavouriteController extends Controller
{
    public function index(Request $request)
    {
        $vendors = VendorProfile::with('category')
            ->whereIn('id', $request->user()->favourites()->pluck('vendor_profile_id'))
            ->get();

        return VendorProfileResource::collection($vendors);
    }

    public function toggle(Request $request, VendorProfile $vendor)
    {
        $existing = Favourite::where('user_id', $request->user()->id)
            ->where('vendor_profile_id', $vendor->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['favourited' => false, 'message' => 'Removed from favourites.']);
        }

        Favourite::create(['user_id' => $request->user()->id, 'vendor_profile_id' => $vendor->id]);

        return response()->json(['favourited' => true, 'message' => 'Saved to favourites.']);
    }
}
