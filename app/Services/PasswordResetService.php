<?php

namespace App\Services;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Forgotten passwords: a 6-digit code is texted to the account's phone and exchanged for a new password.
 * The code is stored hashed, expires quickly, works once and survives only a few wrong guesses.
 * Callers must never reveal whether the account exists.
 */
class PasswordResetService
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const MAX_CODES_PER_HOUR = 3;

    public const COOLDOWN_SECONDS = 60;

    public function __construct(private SmsService $sms, private Notifier $notify) {}

    /** In production a code only helps if SMS really goes out; otherwise users are pointed to support. */
    public function smsAvailable(): bool
    {
        return $this->sms->isLive() || ! app()->isProduction();
    }

    public function findUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $query = str_contains($identifier, '@') ? User::where('email', mb_strtolower($identifier)) : User::where('phone', zm_phone($identifier));

        return $query->where('status', 'active')->first();
    }

    /** Sends a code when the account exists. Silent in every other case. */
    public function requestCode(string $identifier, ?string $ip = null): void
    {
        $user = $this->findUser($identifier);
        if (! $user || ! $user->phone) {
            return;
        }
        $recent = PasswordResetCode::where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->orderByDesc('id')->get();
        if ($recent->count() >= self::MAX_CODES_PER_HOUR || ($recent->first() && $recent->first()->created_at->gt(now()->subSeconds(self::COOLDOWN_SECONDS)))) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        PasswordResetCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]); // older codes stop working
        PasswordResetCode::create([
            'user_id' => $user->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'ip' => $ip,
        ]);

        $this->sms->send($user->phone, "Your password reset code is {$code}. It expires in ".self::TTL_MINUTES.' minutes. Never share it with anyone.',
            'Password reset code sent');
    }

    /** Returns the user when the code was right and the password has been changed, otherwise null. */
    public function reset(string $identifier, string $code, string $newPassword): ?User
    {
        $user = $this->findUser($identifier);
        if (! $user) {
            return null;
        }
        $row = PasswordResetCode::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $row || $row->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }
        $row->increment('attempts');
        if (! Hash::check(trim($code), $row->code_hash)) {
            return null;
        }

        DB::transaction(function () use ($user, $newPassword) {
            $user->forceFill(['password' => $newPassword, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
            PasswordResetCode::where('user_id', $user->id)->delete();
            $user->tokens()->delete(); // mobile app sessions
        });

        $this->notify->user($user, 'Password changed', 'Your HANDY NOW password was just changed. If this was not you, contact support immediately.', null, 'warning', true);

        return $user;
    }

    /** Admin helper: a readable temporary password that satisfies the password rules. */
    public static function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $pick = fn (string $set, int $n) => collect(range(1, $n))->map(fn () => $set[random_int(0, strlen($set) - 1)])->implode('');

        return str_shuffle($pick($letters, 6).$pick($digits, 3).$pick($letters, 1)).'';
    }
}
