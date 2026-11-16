<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderJobController extends Controller
{
    public function available(Request $request)
    {
        $id = $request->user()->id;

        return ServiceRequestResource::collection(ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereHas('matches', fn ($m) => $m->where('provider_id', $id)->where('status', '!=', 'declined'))
            ->with(['category', 'service'])->latest()->paginate(20));
    }

    public function mine(Request $request)
    {
        return ServiceRequestResource::collection($request->user()->providerJobs()->with(['category', 'service'])->latest()->paginate(20));
    }

    public function quote(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'callout_fee' => 'nullable|numeric|min:0|max:100000',
            'eta_minutes' => 'required|integer|min:5|max:1440',
            'message' => 'nullable|string|max:1000',
        ]);
        abort_unless($serviceRequest->matches()->where('provider_id', $request->user()->id)->exists(), 404);
        $q = $wf->submitQuote($serviceRequest, $request->user(), $data);

        return response()->json(['data' => ['id' => $q->id, 'total' => $q->total(), 'status' => $q->status]], 201);
    }

    public function status(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ServiceRequest::STATUSES))], 'note' => 'nullable|string|max:300']);
        $wf->providerUpdateStatus($serviceRequest, $request->user(), $data['status'], $data['note'] ?? null);

        return new ServiceRequestResource($serviceRequest->fresh()->load(['category', 'statusLogs']));
    }

    public function availability(Request $request, RequestWorkflow $wf)
    {
        $data = $request->validate(['online' => 'required|boolean']);
        $profile = $request->user()->providerProfile;
        abort_unless($profile->isApproved(), 422, 'Your account must be verified first.');
        $profile->update(['is_online' => $data['online']]);
        if ($data['online']) {
            $wf->matchOpenRequestsFor($profile);
        }

        return response()->json(['online' => (bool) $data['online']]);
    }

    public function location(Request $request)
    {
        $data = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);
        $request->user()->providerProfile->update(['current_latitude' => $data['lat'], 'current_longitude' => $data['lng'], 'location_updated_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
