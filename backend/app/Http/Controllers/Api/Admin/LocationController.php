<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        return Location::orderBy('city')->get()->map(function ($loc) {
            $loc->vendor_count = VendorProfile::where('city', $loc->city)->count();

            return $loc;
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city' => ['required', 'string', 'max:120', 'unique:locations,city'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        return response()->json(Location::create($data), 201);
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return response()->json(['message' => 'Location removed.']);
    }
}
