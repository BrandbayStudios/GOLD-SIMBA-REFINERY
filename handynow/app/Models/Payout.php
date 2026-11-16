<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payout extends Model
{
    protected $fillable = ['reference', 'provider_id', 'amount', 'method', 'destination', 'account_name', 'status', 'transaction_reference', 'note',
        'created_by', 'paid_by', 'paid_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Payout $p) => $p->reference ??= 'PO-'.strtoupper(Str::random(6)));
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
