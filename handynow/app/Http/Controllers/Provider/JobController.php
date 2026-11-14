<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Services\Geo;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'new');
        $q = ServiceRequest::with('category')->latest();
        match ($tab) {
            'active' => $q->where('provider_id', $user->id)->activeJobs(),
            'completed' => $q->where('provider_id', $user->id)->where('status', 'completed'),
            'cancelled' => $q->where('provider_id', $user->id)->where('status', 'cancelled'),
            'quoted' => $q->whereHas('quotations', fn ($x) => $x->where('provider_id', $user->id)),
            default => $q->whereIn('status', ServiceRequest::OPEN_STATUSES)
                ->whereHas('matches', fn ($m) => $m->where('provider_id', $user->id)->where('status', '!=', 'declined')),
        };
        $jobs = $q->paginate(15)->withQueryString();

        return view('provider.jobs.index', compact('jobs', 'tab'));
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();
        $this->authorizeView($request, $serviceRequest);
        $serviceRequest->load(['category', 'service', 'customer', 'statusLogs', 'payments', 'review']);
        $myQuote = $serviceRequest->quotations()->where('provider_id', $user->id)->first();
        $profile = $user->providerProfile;
        $distance = Geo::distanceKm($serviceRequest->latitude, $serviceRequest->longitude, $profile->latitude(), $profile->longitude());
        $isAssigned = $serviceRequest->provider_id === $user->id;

        return view('provider.jobs.show', ['sr' => $serviceRequest, 'myQuote' => $myQuote, 'distance' => $distance, 'isAssigned' => $isAssigned]);
    }

    public function quote(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->authorizeView($request, $serviceRequest);
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'callout_fee' => 'nullable|numeric|min:0|max:100000',
            'eta_minutes' => 'required|integer|min:5|max:1440',
            'message' => 'nullable|string|max:1000',
        ]);
        $wf->submitQuote($serviceRequest, $request->user(), $data);

        return back()->with('success', 'Quotation sent to the customer.');
    }

    public function decline(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->authorizeView($request, $serviceRequest);
        $wf->declineJob($serviceRequest, $request->user());

        return redirect()->route('provider.jobs.index')->with('success', 'Job declined.');
    }

    public function status(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ServiceRequest::STATUSES))],
            'note' => 'nullable|string|max:300',
        ]);
        $wf->providerUpdateStatus($serviceRequest, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Status updated to "'.ServiceRequest::STATUSES[$data['status']].'". The customer has been notified.');
    }

    /** Provider withdraws from an assigned job before starting; the request is cancelled and admins are alerted. */
    public function release(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        abort_unless($serviceRequest->provider_id === $request->user()->id, 403);
        abort_unless(in_array($serviceRequest->status, [ServiceRequest::STATUS_ASSIGNED, ServiceRequest::STATUS_ACCEPTED], true), 422);
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $wf->cancel($serviceRequest, $request->user(), 'Provider withdrew: '.$data['reason']);

        return redirect()->route('provider.jobs.index', ['tab' => 'active'])->with('success', 'You have withdrawn from the job.');
    }

    public function confirmPayment(Request $request, Payment $payment, RequestWorkflow $wf)
    {
        abort_unless($payment->provider_id === $request->user()->id, 403);
        $wf->confirmPayment($payment, $request->user());

        return back()->with('success', 'Payment confirmed. Commission has been calculated.');
    }

    public function rejectPayment(Request $request, Payment $payment, RequestWorkflow $wf)
    {
        abort_unless($payment->provider_id === $request->user()->id && $payment->status === 'pending', 403);
        $wf->rejectPayment($payment, $request->user());

        return back()->with('success', 'Payment marked as not received. The customer has been notified.');
    }

    private function authorizeView(Request $request, ServiceRequest $sr): void
    {
        $id = $request->user()->id;
        $allowed = $sr->provider_id === $id
            || $sr->matches()->where('provider_id', $id)->exists()
            || $sr->quotations()->where('provider_id', $id)->exists();
        abort_unless($allowed, 404);
    }
}
