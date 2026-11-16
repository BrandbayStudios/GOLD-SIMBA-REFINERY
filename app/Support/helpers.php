<?php

if (! function_exists('zmw')) {
    /** Format an amount in Zambian Kwacha. */
    function zmw($amount, int $decimals = 2): string
    {
        return 'K'.number_format((float) $amount, $decimals);
    }
}

if (! function_exists('zm_phone')) {
    /** Normalise a Zambian phone number to international format without "+" (e.g. 260971234567). */
    function zm_phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '260')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '260'.substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '260'.$digits;
        }

        return $digits;
    }
}

if (! function_exists('whatsapp_link')) {
    function whatsapp_link(?string $phone, string $text = ''): string
    {
        return 'https://wa.me/'.zm_phone($phone).($text !== '' ? '?text='.rawurlencode($text) : '');
    }
}

if (! function_exists('status_badge_class')) {
    function status_badge_class(string $status): string
    {
        return match ($status) {
            'completed', 'approved', 'confirmed', 'paid', 'resolved', 'active', 'accepted' => 'badge-green',
            'cancelled', 'rejected', 'suspended', 'failed', 'dismissed' => 'badge-red',
            'pending', 'unpaid', 'open' => 'badge-grey',
            'searching', 'investigating' => 'badge-amber',
            default => 'badge-blue',
        };
    }
}
