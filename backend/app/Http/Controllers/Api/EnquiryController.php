<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function store(Request $request, VendorProfile $vendor)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'guest_name' => [$user ? 'nullable' : 'required', 'string', 'max:255'],
            'guest_phone' => [$user ? 'nullable' : 'required', 'string', 'max:30'],
            'event_date' => ['nullable', 'date'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $enquiry = $vendor->enquiries()->create([
            'user_id' => $user?->id,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_phone' => $data['guest_phone'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'guest_count' => $data['guest_count'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Your enquiry has been sent to '.$vendor->business_name.'.',
            'enquiry' => $enquiry,
        ], 201);
    }
}
