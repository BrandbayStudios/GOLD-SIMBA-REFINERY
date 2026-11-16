<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Callbacks from MTN MoMo / Airtel Money. The callback body is only used to find the payment:
 * the result is always re-checked with the operator's API before anything is confirmed,
 * so a forged callback cannot mark a payment as paid.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, RequestWorkflow $wf)
    {
        $payment = match ($gateway) {
            'mtn_momo' => Payment::where('gateway', 'mtn_momo')->where(function ($q) use ($request) {
                $q->where('id', (int) $request->input('externalId'))
                    ->orWhere('gateway_reference', (string) ($request->header('X-Reference-Id') ?? $request->input('referenceId')));
            })->first(),
            'airtel_money' => Payment::where('gateway', 'airtel_money')->where('gateway_reference', (string) $request->input('transaction.id'))->first(),
            default => null,
        };

        if ($payment) {
            try {
                $wf->syncPayment($payment);
            } catch (Throwable $e) {
                Log::warning("Payment webhook sync failed for payment {$payment->id}: ".$e->getMessage());
            }
        }

        return response()->json(['received' => true]);
    }
}
