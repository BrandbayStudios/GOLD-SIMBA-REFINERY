<?php

namespace App\Services\Sms;

use App\Services\Payments\GatewaySettings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Africa's Talking bulk SMS (https://africastalking.com) – reaches MTN, Airtel and Zamtel numbers in Zambia.
 * Username "sandbox" uses the sandbox API (messages appear in the Africa's Talking simulator, not on phones).
 */
class AfricasTalkingSmsDriver implements SmsDriver
{
    public function __construct(
        private string $username,
        private string $apiKey,
        private ?string $senderId = null,
    ) {}

    public static function fromSettings(GatewaySettings $cfg): ?self
    {
        if (! $cfg->enabled('at') || ! $cfg->has('at_username') || ! $cfg->has('at_api_key')) {
            return null;
        }

        return new self($cfg->get('at_username'), $cfg->get('at_api_key'), $cfg->get('at_sender_id'));
    }

    public function isSandbox(): bool
    {
        return $this->username === 'sandbox';
    }

    private function host(string $service): string
    {
        return 'https://'.$service.($this->isSandbox() ? '.sandbox' : '').'.africastalking.com';
    }

    public function send(string $to, string $message): array
    {
        try {
            $res = Http::asForm()->acceptJson()->withHeaders(['apiKey' => $this->apiKey])->timeout(15)
                ->post($this->host('api').'/version1/messaging', array_filter([
                    'username' => $this->username,
                    'to' => '+'.ltrim($to, '+'),
                    'message' => $message,
                    'from' => $this->senderId ?: null,
                ]));
        } catch (Throwable $e) {
            return ['status' => 'failed', 'response' => mb_substr($e->getMessage(), 0, 1000)];
        }

        // Africa's Talking answers 201 even when a recipient is rejected, so read the per-recipient status.
        $recipient = $res->json('SMSMessageData.Recipients.0');
        $ok = $res->successful() && $recipient && in_array((int) ($recipient['statusCode'] ?? 0), [100, 101, 102], true);
        $summary = $recipient
            ? ($recipient['status'] ?? '').' '.($recipient['cost'] ?? '').' '.($recipient['messageId'] ?? '')
            : ($res->json('SMSMessageData.Message') ?? $res->body());

        return ['status' => $ok ? 'sent' : 'failed', 'response' => mb_substr(trim('HTTP '.$res->status().' '.$summary), 0, 1000)];
    }

    /** Account balance, e.g. "ZMW 120.0000", or null when it cannot be read. */
    public function balance(): ?string
    {
        try {
            $res = Http::acceptJson()->withHeaders(['apiKey' => $this->apiKey])->timeout(8)
                ->get($this->host('api').'/version1/user', ['username' => $this->username]);

            return $res->successful() ? $res->json('UserData.balance') : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function name(): string
    {
        return $this->isSandbox() ? 'africastalking-sandbox' : 'africastalking';
    }
}
