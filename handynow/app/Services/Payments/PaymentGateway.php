<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * A way of collecting a payment.
 * - ManualGateway: customer pays outside the app, provider/admin confirms receipt.
 * - MtnMomoGateway / AirtelMoneyGateway: a PIN prompt is pushed to the payer's phone and the
 *   result is confirmed by the operator's API (callback + status polling), never by the customer.
 */
interface PaymentGateway
{
    public const SUCCESSFUL = 'successful';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /** Start collecting; stores the operator reference on the payment. Throws GatewayException on failure. */
    public function initiate(Payment $payment): Payment;

    /** Ask the operator for the current status: one of the constants above. */
    public function checkStatus(Payment $payment): array; // ['status' => ..., 'reason' => ?string]

    /** True when the gateway confirms payments automatically. */
    public function isOnline(): bool;

    public function name(): string;
}
