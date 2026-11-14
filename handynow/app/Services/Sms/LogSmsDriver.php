<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Development driver: records messages in the log and the sms_logs table without sending. */
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): array
    {
        Log::info("[SMS to {$to}] {$message}");

        return ['status' => 'logged', 'response' => null];
    }

    public function name(): string
    {
        return 'log';
    }
}
