<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function index()
    {
        return Advertisement::with('vendorProfile')->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_profile_id' => ['nullable', 'exists:vendor_profiles,id'],
            'title' => ['required', 'string', 'max:255'],
            'placement' => ['required', 'string', 'max:60'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'link_url' => ['nullable', 'url'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $data['status'] = now()->lt($data['starts_at']) ? 'scheduled' : 'running';

        return response()->json(Advertisement::create($data), 201);
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,running,ended'],
        ]);

        $advertisement->update($data);

        return response()->json($advertisement);
    }

    public function destroy(Advertisement $advertisement)
    {
        $advertisement->delete();

        return response()->json(['message' => 'Advertisement removed.']);
    }
}
