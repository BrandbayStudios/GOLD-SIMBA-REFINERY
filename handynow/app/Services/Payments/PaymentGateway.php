<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Contract for payment gateways (MTN MoMo, Airtel Money, Zamtel Kwacha, bank).
 * The MVP ships with ManualGateway: the customer records the payment, then the provider or an admin confirms it.
 * A live gateway would implement initiate() to push a USSD/STK prompt and confirm via webhook.
 */
interface PaymentGateway
{
    /** Start collecting a payment. Returns the payment, possibly already confirmed. */
    public function initiate(Payment $payment): Payment;

    public function name(): string;
}
