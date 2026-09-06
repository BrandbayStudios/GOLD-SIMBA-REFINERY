<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->events()->with('vendorProfiles')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
        ]);

        $event = $request->user()->events()->create($data);

        return response()->json($event, 201);
    }

    public function addVendor(Request $request, Event $event, VendorProfile $vendor)
    {
        abort_unless($event->user_id === $request->user()->id, 403);

        $event->vendorProfiles()->syncWithoutDetaching([$vendor->id]);

        return response()->json(['message' => $vendor->business_name.' saved to '.$event->name.'.']);
    }
}
