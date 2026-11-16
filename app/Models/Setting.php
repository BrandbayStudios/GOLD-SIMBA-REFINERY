<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'commission_rate' => '10',          // percent taken by the platform on completed jobs
        'booking_fee' => '0',               // ZMW charged to the customer in the app once a provider is confirmed (0 = off)
        'booking_fee_instructions' => '',   // where customers send the booking fee while mobile money prompts are not live
        'variation_limit_percent' => '50',  // extra cost a provider may add on site, as % of the agreed price
        'min_payout' => '100',              // balance (ZMW) at which a provider is flagged as due a payout
        'commission_instructions' => '',    // where providers send commission they owe, shown on their earnings page
        'match_radius_km' => '30',          // default search radius for provider matching
        'support_phone' => '+260 970 000 000',
        'support_whatsapp' => '260970000000',
        'support_email' => 'support@handynow.co.zm',
    ];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
