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
