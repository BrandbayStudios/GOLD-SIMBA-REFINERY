<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = [
        'mtn_momo' => 'MTN Mobile Money',
        'airtel_money' => 'Airtel Money',
        'zamtel_kwacha' => 'Zamtel Kwacha',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
    ];

    protected $fillable = [
        'service_request_id', 'customer_id', 'provider_id', 'amount', 'currency', 'method', 'payer_phone', 'transaction_reference',
        'gateway', 'gateway_reference', 'gateway_status', 'failure_reason', 'status', 'commission_rate', 'commission_amount', 'provider_amount', 'confirmed_by', 'confirmed_at', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'commission_amount' => 'decimal:2', 'provider_amount' => 'decimal:2',
            'confirmed_at' => 'datetime', 'is_demo' => 'boolean',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** Mobile network prefixes (after 260 / 0) used to catch numbers entered under the wrong network. */
    public const NETWORK_PREFIXES = ['mtn_momo' => ['96', '76'], 'airtel_money' => ['97', '77'], 'zamtel_kwacha' => ['95', '75']];

    public function isOnline(): bool
    {
        return in_array($this->gateway, ['mtn_momo', 'airtel_money'], true);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
