<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /** @return array{status: string, response: ?string} */
    public function send(string $to, string $message): array;

    public function name(): string;
}
