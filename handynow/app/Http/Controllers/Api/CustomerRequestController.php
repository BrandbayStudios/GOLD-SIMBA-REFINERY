<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TrackingController;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerRequestController extends Controller
{
    public function index(Request $request)
    {
        return ServiceRequestResource::collection(
            $request->user()->customerRequests()->with(['category', 'service', 'provider.providerProfile'])->latest()->paginate(20)
        );
    }

    public function store(StoreServiceRequest $request, RequestWorkflow $wf)
    {
        $sr = $wf->create($request->validated(), $request->user(), $request->file('photos', []));

        return (new ServiceRequestResource($sr->load(['category', 'service', 'statusLogs'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->own($request, $serviceRequest);

        return new ServiceRequestResource($serviceRequest->load(['category', 'service', 'provider.providerProfile', 'quotations.provider.providerProfile', 'statusLogs']));
    }

    public function live(Request $request, ServiceRequest $serviceRequest)
    {
        $this->own($request, $serviceRequest);

        return response()->json(TrackingController::livePayload($serviceRequest));
    }

    public function acceptQuote(Request $request, ServiceRequest $serviceRequest, Quotation $quotation, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);
        abort_unless($quotation->service_request_id === $serviceRequest->id, 404);

        return new ServiceRequestResource($wf->acceptQuote($quotation, $request->user())->load(['category', 'provider.providerProfile']));
    }

    public function rejectQuote(Request $request, ServiceRequest $serviceRequest, Quotation $quotation, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);
        abort_unless($quotation->service_request_id === $serviceRequest->id, 404);
        $wf->rejectQuote($quotation);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);
        abort_unless($serviceRequest->canBeCancelledByCustomer(), 422, 'This request can no longer be cancelled.');
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $wf->cancel($serviceRequest, $request->user(), 'Cancelled by customer: '.$data['reason']);

        return response()->json(['ok' => true]);
    }

    public function pay(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'payer_phone' => 'nullable|string|max:20',
            'transaction_reference' => 'nullable|string|max:100',
        ]);
        $payment = $wf->recordPayment($serviceRequest, $data + ['amount' => $serviceRequest->agreed_amount], $request->user());

        return response()->json(['data' => ['id' => $payment->id, 'status' => $payment->status, 'amount' => $payment->amount]], 201);
    }

    public function review(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:1000']);
        $wf->review($serviceRequest, (int) $data['rating'], $data['comment'] ?? null);

        return response()->json(['ok' => true], 201);
    }

    private function own(Request $request, ServiceRequest $sr): void
    {
        abort_unless($sr->customer_id === $request->user()->id, 404);
    }
}
