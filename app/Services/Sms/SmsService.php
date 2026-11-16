<?php

namespace App\Services\Sms;

use App\Models\SmsLog;

class SmsService
{
    public function __construct(private SmsDriver $driver) {}

    public function send(?string $phone, string $message): void
    {
        if (! $phone) {
            return;
        }
        $to = zm_phone($phone);
        $message = mb_substr('HANDY NOW: '.$message, 0, 459);
        $result = $this->driver->send($to, $message);

        SmsLog::create([
            'to' => $to,
            'message' => $message,
            'driver' => $this->driver->name(),
            'status' => $result['status'],
            'response' => $result['response'],
        ]);
    }
}
