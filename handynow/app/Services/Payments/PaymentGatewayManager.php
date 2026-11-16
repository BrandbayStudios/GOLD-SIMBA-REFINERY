<?php

namespace App\Services\Payments;

/** Chooses the gateway for a payment method: live mobile money when configured, otherwise manual confirmation. */
class PaymentGatewayManager
{
    public function __construct(
        private ManualGateway $manual,
        private MtnMomoGateway $mtn,
        private AirtelMoneyGateway $airtel,
    ) {}

    public function forMethod(string $method): PaymentGateway
    {
        return match (true) {
            $method === 'mtn_momo' && $this->mtn->isConfigured() => $this->mtn,
            $method === 'airtel_money' && $this->airtel->isConfigured() => $this->airtel,
            default => $this->manual,
        };
    }

    public function byName(string $name): PaymentGateway
    {
        return match ($name) {
            'mtn_momo' => $this->mtn,
            'airtel_money' => $this->airtel,
            default => $this->manual,
        };
    }

    /** Methods that currently use an automatic in-app prompt. */
    public function onlineMethods(): array
    {
        return array_values(array_filter(['mtn_momo', 'airtel_money'], fn ($m) => $this->forMethod($m)->isOnline()));
    }

    public function status(): array
    {
        return ['mtn_momo' => $this->mtn->isConfigured(), 'airtel_money' => $this->airtel->isConfigured()];
    }
}
