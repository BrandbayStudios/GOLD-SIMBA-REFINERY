<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * MTN Mobile Money – Collections API "Request to Pay" (https://momodeveloper.mtn.com).
 * The payer receives a USSD prompt and approves with their MoMo PIN.
 */
class MtnMomoGateway implements PaymentGateway
{
    public function __construct(private GatewaySettings $cfg) {}

    public function name(): string
    {
        return 'mtn_momo';
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return $this->cfg->enabled('mtn') && $this->cfg->has('mtn_subscription_key') && $this->cfg->has('mtn_api_user') && $this->cfg->has('mtn_api_key');
    }

    public function baseUrl(): string
    {
        return $this->cfg->get('mtn_environment') === 'production' ? 'https://proxy.momoapi.mtn.com' : 'https://sandbox.momodeveloper.mtn.com';
    }

    private function targetEnvironment(): string
    {
        return $this->cfg->get('mtn_target_environment') ?: ($this->cfg->get('mtn_environment') === 'production' ? 'mtnzambia' : 'sandbox');
    }

    private function currency(): string
    {
        // The MTN sandbox only accepts EUR; production Zambia uses ZMW.
        return $this->cfg->get('mtn_currency') ?: ($this->cfg->get('mtn_environment') === 'production' ? 'ZMW' : 'EUR');
    }

    private function token(): string
    {
        $key = 'mtn_momo_token_'.md5($this->cfg->get('mtn_api_user').$this->baseUrl());

        return Cache::remember($key, 3000, function () {
            $res = Http::withBasicAuth((string) $this->cfg->get('mtn_api_user'), (string) $this->cfg->get('mtn_api_key'))
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->cfg->get('mtn_subscription_key')])
                ->timeout(20)->post($this->baseUrl().'/collection/token/');
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new GatewayException('MTN MoMo authentication failed ('.$res->status().').');
            }

            return $res->json('access_token');
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token(),
            'X-Target-Environment' => $this->targetEnvironment(),
            'Ocp-Apim-Subscription-Key' => $this->cfg->get('mtn_subscription_key'),
        ];
    }

    public function initiate(Payment $payment): Payment
    {
        $reference = (string) Str::uuid();
        try {
            $res = Http::withHeaders($this->headers() + [
                'X-Reference-Id' => $reference,
                'X-Callback-Url' => route('webhooks.payments', 'mtn_momo'),
            ])->timeout(30)->post($this->baseUrl().'/collection/v1_0/requesttopay', [
                'amount' => (string) round((float) $payment->amount, 2),
                'currency' => $this->currency(),
                'externalId' => (string) $payment->id,
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => zm_phone($payment->payer_phone)],
                'payerMessage' => 'HANDY NOW '.$payment->serviceRequest->reference,
                'payeeNote' => 'HANDY NOW payment '.$payment->id,
            ]);
        } catch (GatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GatewayException('Could not reach MTN MoMo. Please try again.');
        }
        if ($res->status() !== 202) {
            throw new GatewayException('MTN MoMo declined the request ('.$res->status().'): '.($res->json('message') ?? 'unknown error'));
        }
        $payment->update(['gateway_reference' => $reference, 'gateway_status' => 'PENDING']);

        return $payment;
    }

    public function checkStatus(Payment $payment): array
    {
        if (! $payment->gateway_reference) {
            return ['status' => self::FAILED, 'reason' => 'No MTN reference'];
        }
        $res = Http::withHeaders($this->headers())->timeout(20)
            ->get($this->baseUrl().'/collection/v1_0/requesttopay/'.$payment->gateway_reference);
        if (! $res->successful()) {
            return ['status' => self::PENDING, 'reason' => null];
        }
        $status = strtoupper((string) $res->json('status'));
        $payment->update(['gateway_status' => $status]);

        return match ($status) {
            'SUCCESSFUL' => ['status' => self::SUCCESSFUL, 'reason' => $res->json('financialTransactionId')],
            'FAILED', 'REJECTED', 'TIMEOUT' => ['status' => self::FAILED, 'reason' => str_replace('_', ' ', strtolower((string) ($res->json('reason') ?? $status)))],
            default => ['status' => self::PENDING, 'reason' => null],
        };
    }
}
