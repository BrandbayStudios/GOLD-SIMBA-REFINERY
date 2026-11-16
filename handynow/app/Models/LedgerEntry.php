<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public const TYPES = [
        'earning' => 'Earning',
        'commission_due' => 'Commission owed',
        'payout' => 'Payout',
        'payout_reversal' => 'Payout cancelled',
        'commission_paid' => 'Commission received',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = ['provider_id', 'payment_id', 'payout_id', 'type', 'amount', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
