<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Geo;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $q = ServiceRequest::with(['category', 'provider', 'customer'])->latest();
        if ($s = $request->query('status')) {
            $s === 'active' ? $q->activeJobs() : $q->where('status', $s);
        }
        if ($p = $request->query('priority')) {
            $q->where('priority', $p);
        }
        if ($c = $request->query('category')) {
            $q->where('category_id', $c);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('contact_phone', 'like', "%{$term}%")->orWhere('address', 'like', "%{$term}%"));
        }
        $requests = $q->paginate(20)->withQueryString();
        $mapPoints = ServiceRequest::activeJobs()->whereNotNull('latitude')->with('category')->get()
            ->map(fn ($r) => ['lat' => $r->latitude, 'lng' => $r->longitude, 'ref' => $r->reference, 'cat' => $r->category->name,
                'priority' => $r->priority, 'status' => $r->statusLabel(), 'url' => route('admin.requests.show', $r)]);
        $providerPoints = ProviderProfile::available()->with('user')->get()->filter(fn ($p) => $p->latitude())
            ->map(fn ($p) => ['lat' => $p->latitude(), 'lng' => $p->longitude(), 'name' => $p->displayName()])->values();

        return view('admin.requests.index', [
            'requests' => $requests,
            'categories' => Category::orderBy('name')->get(),
            'statuses' => ServiceRequest::STATUSES,
            'mapPoints' => $mapPoints,
            'providerPoints' => $providerPoints,
        ]);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $sr = $serviceRequest->load(['category', 'service', 'customer', 'provider.providerProfile', 'quotations.provider', 'statusLogs.user',
            'payments', 'review', 'matches.provider', 'messages.sender', 'complaints']);
        $eligible = ProviderProfile::where('verification_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $sr->category_id))
            ->with('user')->get()
            ->map(fn ($p) => ['profile' => $p, 'distance' => Geo::distanceKm($sr->latitude, $sr->longitude, $p->latitude(), $p->longitude())])
            ->sortBy(fn ($x) => $x['distance'] ?? 9999)->values();

        return view('admin.requests.show', compact('sr', 'eligible'));
    }

    public function assign(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $data = $request->validate(['provider_id' => 'required|integer|exists:users,id']);
        abort_unless($serviceRequest->isOpen(), 422, 'Only open requests can be assigned.');
        $wf->assignDirectly($serviceRequest, User::findOrFail($data['provider_id']), $request->user());

        return back()->with('success', 'Provider assigned and notified.');
    }

    public function rematch(ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $n = $wf->match($serviceRequest);

        return back()->with('success', $n ? "{$n} additional provider(s) notified." : 'No additional eligible providers are online nearby.');
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $wf->cancel($serviceRequest, $request->user(), 'Cancelled by HANDY NOW: '.$data['reason']);

        return back()->with('success', 'Request cancelled.');
    }

    public function confirmPayment(Request $request, Payment $payment, RequestWorkflow $wf)
    {
        $wf->confirmPayment($payment, $request->user());

        return back()->with('success', 'Payment confirmed and commission recorded.');
    }

    public function rejectPayment(Request $request, Payment $payment, RequestWorkflow $wf)
    {
        abort_unless($payment->status === 'pending', 422);
        $wf->rejectPayment($payment, $request->user());

        return back()->with('success', 'Payment rejected.');
    }

    public function payments(Request $request)
    {
        $q = Payment::with(['serviceRequest', 'customer', 'provider'])->latest();
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($m = $request->query('method')) {
            $q->where('method', $m);
        }
        $payments = $q->paginate(25)->withQueryString();
        $totals = Payment::where('status', 'confirmed')->selectRaw('COALESCE(SUM(amount),0) as gross, COALESCE(SUM(commission_amount),0) as commission')->first();

        return view('admin.payments', compact('payments', 'totals'));
    }
}
