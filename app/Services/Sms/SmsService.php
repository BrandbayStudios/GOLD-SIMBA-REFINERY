<?php

namespace App\Services\Sms;

use App\Models\SmsLog;

class SmsService
{
    public function __construct(private SmsDriver $driver) {}

    /** True when messages really reach phones (a gateway is switched on), not just the log. */
    public function isLive(): bool
    {
        return ! $this->driver instanceof LogSmsDriver;
    }

    /** $logAs replaces the text stored in the SMS log (used to keep one-time codes out of it). */
    public function send(?string $phone, string $message, ?string $logAs = null): void
    {
        if (! $phone) {
            return;
        }
        $to = zm_phone($phone);
        $message = mb_substr('HANDY NOW: '.$message, 0, 459);
        $result = $this->driver->send($to, $message);

        SmsLog::create([
            'to' => $to,
            'message' => $logAs !== null ? mb_substr('HANDY NOW: '.$logAs, 0, 459) : $message,
            'driver' => $this->driver->name(),
            'status' => $result['status'],
            'response' => $result['response'],
        ]);
    }
}
