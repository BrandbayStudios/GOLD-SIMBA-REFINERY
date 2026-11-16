<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Complaint extends Model
{
    public const STATUSES = ['open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    protected $fillable = ['reference', 'user_id', 'service_request_id', 'against_user_id', 'subject', 'description', 'status',
        'admin_response', 'handled_by', 'resolved_at', 'is_demo'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Complaint $c) => $c->reference ??= 'CP-'.strtoupper(Str::random(6)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function againstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
