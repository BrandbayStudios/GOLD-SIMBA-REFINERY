<?php

namespace App\Services\Payments;

use App\Models\Payment;

class ManualGateway implements PaymentGateway
{
    public function initiate(Payment $payment): Payment
    {
        // Nothing to call: payment stays "pending" until a provider or admin confirms receipt.
        return $payment;
    }

    public function name(): string
    {
        return 'manual';
    }
}
