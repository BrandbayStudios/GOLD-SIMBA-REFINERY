<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\HandlesCustomerRequestActions;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    use HandlesCustomerRequestActions;

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $q = $request->user()->customerRequests()->with(['category', 'provider'])->latest();
        match ($filter) {
            'active' => $q->activeJobs(),
            'completed' => $q->where('status', 'completed'),
            'cancelled' => $q->where('status', 'cancelled'),
            default => null,
        };
        $requests = $q->paginate(12)->withQueryString();

        return view('customer.requests.index', compact('requests', 'filter'));
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->own($request, $serviceRequest);
        $serviceRequest->load(['category', 'service', 'provider.providerProfile', 'quotations.provider.providerProfile', 'statusLogs', 'payments', 'review']);

        return view('requests.track', ['sr' => $serviceRequest, 'guest' => false]);
    }

    public function acceptQuote(Request $request, ServiceRequest $serviceRequest, Quotation $quotation, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);

        return $this->doAcceptQuote($serviceRequest, $quotation, $request, $wf);
    }

    public function rejectQuote(Request $request, ServiceRequest $serviceRequest, Quotation $quotation, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);

        return $this->doRejectQuote($serviceRequest, $quotation, $wf);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);

        return $this->doCancel($serviceRequest, $request, $wf);
    }

    public function pay(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);

        return $this->doPay($serviceRequest, $request, $wf);
    }

    public function review(Request $request, ServiceRequest $serviceRequest, RequestWorkflow $wf)
    {
        $this->own($request, $serviceRequest);

        return $this->doReview($serviceRequest, $request, $wf);
    }

    private function own(Request $request, ServiceRequest $sr): void
    {
        abort_unless($sr->customer_id === $request->user()->id, 404);
    }
}
