<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Models\Vendor;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_slug' => ['nullable', 'string'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'name'        => ['required', 'string', 'max:255'],
            'phone'       => ['required', 'string', 'max:40'],
            'event_type'  => ['nullable', 'string', 'max:120'],
            'event_date'  => ['nullable', 'date'],
            'location'    => ['nullable', 'string', 'max:255'],
            'guest_count' => ['nullable', 'integer', 'min:0'],
            'requirements' => ['nullable', 'string'],
        ]);

        $vendor = Vendor::where('slug', $data['vendor_slug'] ?? null)->first();

        QuoteRequest::create([
            'user_id'     => $request->user()->id,
            'vendor_id'   => $vendor?->id,
            'vendor_name' => $data['vendor_name'] ?? $vendor?->name,
            'name'        => $data['name'],
            'phone'       => $data['phone'],
            'event_type'  => $data['event_type'] ?? null,
            'event_date'  => $data['event_date'] ?? null,
            'location'    => $data['location'] ?? null,
            'guest_count' => $data['guest_count'] ?? 0,
            'requirements' => $data['requirements'] ?? null,
            'status'      => 'new',
        ]);

        return response()->json(['ok' => true, 'message' => 'Quote request sent to the vendor.']);
    }
}
