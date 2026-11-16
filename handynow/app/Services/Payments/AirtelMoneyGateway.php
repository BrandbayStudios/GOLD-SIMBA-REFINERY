<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Airtel Money – Collections API "USSD Push" (https://developers.airtel.africa), Zambia (ZM / ZMW).
 * The payer receives a prompt and approves with their Airtel Money PIN.
 */
class AirtelMoneyGateway implements PaymentGateway
{
    public function __construct(private GatewaySettings $cfg) {}

    public function name(): string
    {
        return 'airtel_money';
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return $this->cfg->enabled('airtel') && $this->cfg->has('airtel_client_id') && $this->cfg->has('airtel_client_secret');
    }

    public function baseUrl(): string
    {
        return $this->cfg->get('airtel_environment') === 'production' ? 'https://openapi.airtel.africa' : 'https://openapiuat.airtel.africa';
    }

    private function token(): string
    {
        $key = 'airtel_token_'.md5($this->cfg->get('airtel_client_id').$this->baseUrl());

        return Cache::remember($key, 3000, function () {
            $res = Http::acceptJson()->timeout(20)->post($this->baseUrl().'/auth/oauth2/token', [
                'client_id' => $this->cfg->get('airtel_client_id'),
                'client_secret' => $this->cfg->get('airtel_client_secret'),
                'grant_type' => 'client_credentials',
            ]);
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new GatewayException('Airtel Money authentication failed ('.$res->status().').');
            }

            return $res->json('access_token');
        });
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token(), 'X-Country' => 'ZM', 'X-Currency' => 'ZMW', 'Accept' => '*/*'];
    }

    public function initiate(Payment $payment): Payment
    {
        $reference = 'HN'.$payment->id.'T'.now()->format('ymdHis');
        try {
            $res = Http::withHeaders($this->headers())->timeout(30)->post($this->baseUrl().'/merchant/v2/payments/', [
                'reference' => 'HANDY NOW '.$payment->serviceRequest->reference,
                'subscriber' => ['country' => 'ZM', 'currency' => 'ZMW', 'msisdn' => substr(zm_phone($payment->payer_phone), 3)],
                'transaction' => ['amount' => round((float) $payment->amount, 2), 'country' => 'ZM', 'currency' => 'ZMW', 'id' => $reference],
            ]);
        } catch (GatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GatewayException('Could not reach Airtel Money. Please try again.');
        }
        if (! $res->successful() || $res->json('status.success') === false) {
            throw new GatewayException('Airtel Money declined the request: '.($res->json('status.message') ?? 'HTTP '.$res->status()));
        }
        $payment->update(['gateway_reference' => $reference, 'gateway_status' => 'TIP']);

        return $payment;
    }

    public function checkStatus(Payment $payment): array
    {
        if (! $payment->gateway_reference) {
            return ['status' => self::FAILED, 'reason' => 'No Airtel reference'];
        }
        $res = Http::withHeaders($this->headers())->timeout(20)->get($this->baseUrl().'/standard/v1/payments/'.$payment->gateway_reference);
        if (! $res->successful()) {
            return ['status' => self::PENDING, 'reason' => null];
        }
        $status = strtoupper((string) $res->json('data.transaction.status'));
        $payment->update(['gateway_status' => $status]);

        return match ($status) {
            'TS' => ['status' => self::SUCCESSFUL, 'reason' => $res->json('data.transaction.airtel_money_id')],
            'TF', 'TE' => ['status' => self::FAILED, 'reason' => $res->json('data.transaction.message') ?? 'Payment was not completed'],
            default => ['status' => self::PENDING, 'reason' => null],
        };
    }
}
