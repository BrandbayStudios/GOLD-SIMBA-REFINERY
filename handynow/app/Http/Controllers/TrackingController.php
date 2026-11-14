<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCustomerRequestActions;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\Geo;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;

/**
 * Guest-friendly request tracking. Access is granted by the secret token in the SMS link (remembered in the session),
 * by matching reference + phone number, or by being the logged-in owner.
 */
class TrackingController extends Controller
{
    use HandlesCustomerRequestActions;

    public function lookup()
    {
        return view('requests.track-lookup');
    }

    public function find(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:20', 'phone' => 'required|string|max:20']);
        $sr = ServiceRequest::where('reference', strtoupper(trim($data['reference'])))->first();
        if (! $sr || zm_phone($sr->contact_phone) !== zm_phone($data['phone'])) {
            return back()->withInput()->withErrors(['reference' => 'We could not find a request with that reference and phone number.']);
        }
        $request->session()->put('tracked.'.$sr->reference, $sr->tracking_token);

        return redirect()->route('track.show', $sr->reference);
    }

    public function show(Request $request, string $reference)
    {
        $sr = $this->resolve($request, $reference);
        if ($request->user()?->isCustomer() && $sr->customer_id === $request->user()->id) {
            return redirect()->route('customer.requests.show', $sr);
        }
        $sr->load(['category', 'service', 'provider.providerProfile', 'quotations.provider.providerProfile', 'statusLogs', 'payments', 'review']);

        return view('requests.track', ['sr' => $sr, 'guest' => true]);
    }

    /** Polled by the tracking map. */
    public function live(Request $request, string $reference)
    {
        $sr = $this->resolve($request, $reference);

        return response()->json(self::livePayload($sr));
    }

    public static function livePayload(ServiceRequest $sr): array
    {
        $sr->loadMissing('provider.providerProfile');
        $p = $sr->provider?->providerProfile;
        $lat = $p?->latitude();
        $lng = $p?->longitude();
        $km = Geo::distanceKm($sr->latitude, $sr->longitude, $lat, $lng);
        $showProvider = $p && in_array($sr->status, [ServiceRequest::STATUS_ASSIGNED, ServiceRequest::STATUS_ACCEPTED,
            ServiceRequest::STATUS_EN_ROUTE, ServiceRequest::STATUS_ARRIVED, ServiceRequest::STATUS_IN_PROGRESS], true);

        return [
            'status' => $sr->status,
            'status_label' => $sr->statusLabel(),
            'step' => $sr->progressStep(),
            'quotes' => $sr->quotations()->where('status', 'pending')->count(),
            'customer' => ['lat' => $sr->latitude, 'lng' => $sr->longitude],
            'provider' => $showProvider ? [
                'name' => $sr->provider->name,
                'lat' => $lat,
                'lng' => $lng,
                'distance_km' => $km,
                'eta_minutes' => $sr->status === ServiceRequest::STATUS_EN_ROUTE ? Geo::etaMinutes($km) : null,
                'updated' => $p->location_updated_at?->diffForHumans(),
            ] : null,
        ];
    }

    public function acceptQuote(Request $request, string $reference, Quotation $quotation, RequestWorkflow $wf)
    {
        return $this->doAcceptQuote($this->resolve($request, $reference), $quotation, $request, $wf);
    }

    public function rejectQuote(Request $request, string $reference, Quotation $quotation, RequestWorkflow $wf)
    {
        return $this->doRejectQuote($this->resolve($request, $reference), $quotation, $wf);
    }

    public function cancel(Request $request, string $reference, RequestWorkflow $wf)
    {
        return $this->doCancel($this->resolve($request, $reference), $request, $wf);
    }

    public function pay(Request $request, string $reference, RequestWorkflow $wf)
    {
        return $this->doPay($this->resolve($request, $reference), $request, $wf);
    }

    public function review(Request $request, string $reference, RequestWorkflow $wf)
    {
        return $this->doReview($this->resolve($request, $reference), $request, $wf);
    }

    private function resolve(Request $request, string $reference): ServiceRequest
    {
        $sr = ServiceRequest::where('reference', $reference)->firstOrFail();
        $token = $request->query('token');
        if (is_string($token) && hash_equals($sr->tracking_token, $token)) {
            $request->session()->put('tracked.'.$sr->reference, $token);
        }
        $sessionToken = $request->session()->get('tracked.'.$sr->reference);
        $user = $request->user();
        $allowed = ($sessionToken && hash_equals($sr->tracking_token, $sessionToken))
            || ($user && ($user->isAdmin() || $user->id === $sr->customer_id));

        if (! $allowed) {
            abort(403, 'Open the tracking link we sent by SMS, or look up your request with its reference and phone number.');
        }

        return $sr;
    }
}
