<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Single entry point for in-app + SMS notifications. Failures never break the user's action. */
class Notifier
{
    public function __construct(private SmsService $sms) {}

    public function user(?User $user, string $title, string $body, ?string $url = null, string $kind = 'info', bool $sms = false): void
    {
        if (! $user) {
            return;
        }
        try {
            $user->notify(new PlatformNotification($title, $body, $url, $kind));
            if ($sms) {
                $this->sms->send($user->phone, $body);
            }
        } catch (Throwable $e) {
            Log::warning('Notification failed: '.$e->getMessage());
        }
    }

    public function sms(?string $phone, string $message): void
    {
        try {
            $this->sms->send($phone, $message);
        } catch (Throwable $e) {
            Log::warning('SMS failed: '.$e->getMessage());
        }
    }

    public function admins(string $title, string $body, ?string $url = null, string $kind = 'info'): void
    {
        User::where('role', User::ROLE_ADMIN)->where('status', 'active')->get()
            ->each(fn (User $a) => $this->user($a, $title, $body, $url, $kind));
    }
}
