<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorApplicationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name'  => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone'          => ['required', 'string', 'max:40'],
            'email'          => ['required', 'email', 'max:255'],
            'category'       => ['required', 'string', 'max:120'],
            'location'       => ['required', 'string', 'max:120'],
            'description'    => ['nullable', 'string'],
            'facebook_url'   => ['nullable', 'url', 'max:255'],
        ]);

        $user = $request->user();

        // One vendor profile per owner; reuse if they re-apply.
        $vendor = Vendor::firstOrNew(['user_id' => $user->id]);

        if (! $vendor->exists) {
            $vendor->slug = $this->uniqueSlug($data['business_name']);
        }

        $vendor->fill([
            'name'           => $data['business_name'],
            'contact_person' => $data['contact_person'],
            'phone'          => $data['phone'],
            'email'          => $data['email'],
            'category'       => $data['category'],
            'location'       => $data['location'],
            'about'          => $data['description'] ?? null,
            'facebook_url'   => $data['facebook_url'] ?? null,
            'services'       => $vendor->services ?: [$data['category']],
            'event_types'    => $vendor->event_types ?: ['Weddings', 'Birthdays', 'Corporate Events', 'Graduations'],
            'verified'       => false,
            'featured'       => false,
            'status'         => 'pending',
        ])->save();

        // Promote the account to a vendor role.
        $user->update(['role' => 'vendor', 'phone' => $data['phone']]);

        return response()->json([
            'ok'      => true,
            'message' => 'Vendor application submitted. Our team will review and verify your listing.',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'vendor';
        $slug = $base;
        $i = 1;

        while (Vendor::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
