<?php

namespace App\Http\Controllers;

use App\Models\EventPlan;
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'phone'       => ['required', 'string', 'max:40'],
            'event_type'  => ['nullable', 'string', 'max:120'],
            'event_date'  => ['nullable', 'date'],
            'location'    => ['nullable', 'string', 'max:255'],
            'guest_count' => ['nullable', 'integer', 'min:0'],
            'notes'       => ['nullable', 'string'],
        ]);

        EventPlan::create([
            'user_id'     => $request->user()->id,
            'name'        => $data['name'],
            'phone'       => $data['phone'],
            'event_type'  => $data['event_type'] ?? null,
            'event_date'  => $data['event_date'] ?? null,
            'location'    => $data['location'] ?? null,
            'guest_count' => $data['guest_count'] ?? 0,
            'notes'       => $data['notes'] ?? null,
            'status'      => 'new',
        ]);

        return response()->json(['ok' => true, 'message' => 'Your event plan has been saved.']);
    }
}
