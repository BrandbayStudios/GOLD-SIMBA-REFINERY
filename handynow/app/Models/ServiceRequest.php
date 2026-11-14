<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ServiceRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SEARCHING = 'searching';

    public const STATUS_ASSIGNED = 'provider_assigned';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EN_ROUTE = 'en_route';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SEARCHING => 'Searching for Provider',
        self::STATUS_ASSIGNED => 'Provider Assigned',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_EN_ROUTE => 'Provider En Route',
        self::STATUS_ARRIVED => 'Arrived',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /** Statuses a provider may move a job to, keyed by current status. */
    public const PROVIDER_TRANSITIONS = [
        self::STATUS_ASSIGNED => [self::STATUS_ACCEPTED],
        self::STATUS_ACCEPTED => [self::STATUS_EN_ROUTE],
        self::STATUS_EN_ROUTE => [self::STATUS_ARRIVED],
        self::STATUS_ARRIVED => [self::STATUS_IN_PROGRESS],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED],
    ];

    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_SEARCHING];

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_SEARCHING, self::STATUS_ASSIGNED, self::STATUS_ACCEPTED,
        self::STATUS_EN_ROUTE, self::STATUS_ARRIVED, self::STATUS_IN_PROGRESS];

    public const PRIORITIES = ['normal' => 'Normal', 'urgent' => 'Urgent (within hours)', 'emergency' => 'Emergency (right now)'];

    protected $fillable = [
        'reference', 'customer_id', 'provider_id', 'category_id', 'service_id', 'contact_name', 'contact_phone', 'description',
        'address', 'directions', 'latitude', 'longitude', 'priority', 'status', 'photos', 'agreed_amount', 'commission_rate',
        'commission_amount', 'provider_earnings', 'payment_status', 'cancel_reason', 'tracking_token', 'is_demo',
        'assigned_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'is_demo' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'agreed_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'provider_earnings' => 'decimal:2',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $r) {
            $r->reference ??= 'HN-'.strtoupper(Str::random(6));
            $r->tracking_token ??= Str::random(32);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest();
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RequestProviderMatch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SEARCHING, self::STATUS_ASSIGNED, self::STATUS_ACCEPTED], true);
    }

    public function nextProviderStatuses(): array
    {
        return self::PROVIDER_TRANSITIONS[$this->status] ?? [];
    }

    /** Progress index for the tracker UI (0..6). */
    public function progressStep(): int
    {
        $order = [self::STATUS_PENDING, self::STATUS_SEARCHING, self::STATUS_ASSIGNED, self::STATUS_ACCEPTED,
            self::STATUS_EN_ROUTE, self::STATUS_ARRIVED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];
        $i = array_search($this->status, $order, true);

        return $i === false ? 0 : $i;
    }

    public function photoUrls(): array
    {
        return collect($this->photos ?? [])->map(fn ($p) => str_starts_with($p, 'http') || str_starts_with($p, '/') ? $p : asset('storage/'.$p))->all();
    }

    public function trackingUrl(): string
    {
        return route('track.show', ['reference' => $this->reference, 'token' => $this->tracking_token]);
    }

    public function scopeActiveJobs($q)
    {
        return $q->whereIn('status', self::ACTIVE_STATUSES);
    }
}
