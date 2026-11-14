<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Customer-side actions shared by the logged-in dashboard and the guest tracking page. */
trait HandlesCustomerRequestActions
{
    protected function doAcceptQuote(ServiceRequest $sr, Quotation $quotation, Request $request, RequestWorkflow $wf)
    {
        abort_unless($quotation->service_request_id === $sr->id, 404);
        $wf->acceptQuote($quotation, $request->user());

        return back()->with('success', 'Quotation accepted. Your provider has been notified and will confirm shortly.');
    }

    protected function doRejectQuote(ServiceRequest $sr, Quotation $quotation, RequestWorkflow $wf)
    {
        abort_unless($quotation->service_request_id === $sr->id, 404);
        $wf->rejectQuote($quotation);

        return back()->with('success', 'Quotation declined.');
    }

    protected function doCancel(ServiceRequest $sr, Request $request, RequestWorkflow $wf)
    {
        abort_unless($sr->canBeCancelledByCustomer(), 422, 'This request can no longer be cancelled.');
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $wf->cancel($sr, $request->user(), 'Cancelled by customer: '.$data['reason']);

        return back()->with('success', 'Your request has been cancelled.');
    }

    protected function doPay(ServiceRequest $sr, Request $request, RequestWorkflow $wf)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'payer_phone' => 'nullable|required_if:method,mtn_momo,airtel_money,zamtel_kwacha|string|max:20',
            'transaction_reference' => 'nullable|string|max:100',
        ], ['payer_phone.required_if' => 'Enter the mobile money number you paid from.']);
        $data['amount'] = $sr->agreed_amount;
        $wf->recordPayment($sr, $data, $request->user());

        return back()->with('success', 'Payment recorded. Your provider will confirm receipt.');
    }

    protected function doReview(ServiceRequest $sr, Request $request, RequestWorkflow $wf)
    {
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:1000']);
        $wf->review($sr, (int) $data['rating'], $data['comment'] ?? null);

        return back()->with('success', 'Thank you for rating your provider!');
    }
}
