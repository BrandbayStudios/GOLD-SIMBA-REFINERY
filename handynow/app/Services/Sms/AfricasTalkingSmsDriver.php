<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Africa's Talking bulk SMS (supports Zambian MTN, Airtel and Zamtel numbers).
 * Configure AFRICASTALKING_USERNAME, AFRICASTALKING_API_KEY and optionally AFRICASTALKING_SENDER_ID.
 */
class AfricasTalkingSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): array
    {
        $cfg = config('services.africastalking');
        $endpoint = $cfg['username'] === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        try {
            $payload = array_filter([
                'username' => $cfg['username'],
                'to' => '+'.$to,
                'message' => $message,
                'from' => $cfg['sender_id'] ?: null,
            ]);
            $res = Http::asForm()->acceptJson()->withHeaders(['apiKey' => $cfg['api_key']])->timeout(10)->post($endpoint, $payload);

            return ['status' => $res->successful() ? 'sent' : 'failed', 'response' => mb_substr($res->body(), 0, 1000)];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'response' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'africastalking';
    }
}
