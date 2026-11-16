<?php

namespace App\Services\Payments;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Mobile money and SMS credentials, managed in Admin → Settings. Secrets are stored encrypted with the app key;
 * values in .env (MTN_MOMO_*, AIRTEL_MONEY_*) are used as a fallback.
 */
class GatewaySettings
{
    public const SECRET_FIELDS = ['mtn_subscription_key', 'mtn_api_key', 'airtel_client_secret', 'at_api_key'];

    public const FIELDS = [
        'mtn_enabled', 'mtn_environment', 'mtn_target_environment', 'mtn_currency', 'mtn_subscription_key', 'mtn_api_user', 'mtn_api_key',
        'airtel_enabled', 'airtel_environment', 'airtel_client_id', 'airtel_client_secret',
    ];

    /** Africa's Talking SMS (same encrypted storage). */
    public const SMS_FIELDS = ['at_enabled', 'at_username', 'at_api_key', 'at_sender_id'];

    private const ENV = [
        'mtn_enabled' => 'MTN_MOMO_ENABLED', 'mtn_environment' => 'MTN_MOMO_ENVIRONMENT', 'mtn_target_environment' => 'MTN_MOMO_TARGET_ENVIRONMENT',
        'mtn_currency' => 'MTN_MOMO_CURRENCY', 'mtn_subscription_key' => 'MTN_MOMO_SUBSCRIPTION_KEY', 'mtn_api_user' => 'MTN_MOMO_API_USER',
        'mtn_api_key' => 'MTN_MOMO_API_KEY', 'airtel_enabled' => 'AIRTEL_MONEY_ENABLED', 'airtel_environment' => 'AIRTEL_MONEY_ENVIRONMENT',
        'airtel_client_id' => 'AIRTEL_MONEY_CLIENT_ID', 'airtel_client_secret' => 'AIRTEL_MONEY_CLIENT_SECRET',
        'at_username' => 'AFRICASTALKING_USERNAME', 'at_api_key' => 'AFRICASTALKING_API_KEY', 'at_sender_id' => 'AFRICASTALKING_SENDER_ID',
    ];

    public function get(string $field): ?string
    {
        $value = Setting::get('pg_'.$field);
        if ($value !== null && $value !== '' && in_array($field, self::SECRET_FIELDS, true)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (Throwable) {
                $value = null;
            }
        }
        if (($value === null || $value === '') && isset(self::ENV[$field])) {
            $value = env(self::ENV[$field]) ?: null;
        }

        return $value === null ? null : (string) $value;
    }

    public function put(string $field, ?string $value): void
    {
        if (in_array($field, self::SECRET_FIELDS, true) && $value !== null && $value !== '') {
            $value = Crypt::encryptString($value);
        }
        Setting::put('pg_'.$field, $value);
    }

    public function has(string $field): bool
    {
        return (string) $this->get($field) !== '';
    }

    public function enabled(string $prefix): bool
    {
        return filter_var($this->get($prefix.'_enabled'), FILTER_VALIDATE_BOOLEAN);
    }
}
