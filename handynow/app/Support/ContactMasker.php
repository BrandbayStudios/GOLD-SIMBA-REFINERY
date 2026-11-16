<?php

namespace App\Support;

/** Keeps phone numbers and email addresses out of in-app chat, so the booking fee cannot be sidestepped by swapping contacts. */
class ContactMasker
{
    public const NOTE = 'Phone numbers and email addresses are hidden in chat. Please keep the conversation in HANDY NOW.';

    /** @return array{0: string, 1: bool} the cleaned text and whether anything was hidden */
    public static function mask(string $text): array
    {
        $changed = false;
        $text = preg_replace_callback('/(?<![\w])(\+?\d[\d\s().\-]{7,}\d)(?![\w])/u', function ($m) use (&$changed) {
            if (strlen(preg_replace('/\D/', '', $m[1])) >= 9) {
                $changed = true;

                return '[number hidden]';
            }

            return $m[1];
        }, $text);
        $text = preg_replace_callback('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', function () use (&$changed) {
            $changed = true;

            return '[email hidden]';
        }, $text);

        return [$text, $changed];
    }
}
