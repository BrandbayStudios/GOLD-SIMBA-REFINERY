<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderProfile extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'provider_type', 'nrc_number', 'business_reg_number', 'nrc_document', 'business_document',
        'bio', 'years_experience', 'whatsapp', 'operating_areas', 'service_radius_km', 'base_latitude', 'base_longitude',
        'current_latitude', 'current_longitude', 'location_updated_at', 'is_online', 'verification_status', 'verification_notes',
        'verified_at', 'verified_by', 'payout_method', 'payout_phone', 'payout_account_name', 'bank_name', 'bank_account_number', 'bank_branch', 'rating_avg', 'rating_count', 'jobs_completed', 'avg_response_minutes',
    ];

    protected function casts(): array
    {
        return [
            'operating_areas' => 'array',
            'is_online' => 'boolean',
            'verified_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'base_latitude' => 'float',
            'base_longitude' => 'float',
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'rating_avg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_provider');
    }

    public const BANKS = ['Zanaco', 'Stanbic Bank', 'Absa Bank', 'FNB Zambia', 'Standard Chartered', 'Indo-Zambia Bank', 'Access Bank', 'Ecobank', 'First Capital Bank', 'United Bank for Africa (UBA)', 'Citibank Zambia', 'Other'];

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class)->orderBy('id');
    }

    public function requiredDocumentTypes(): array
    {
        return array_merge(['nrc_front', 'nrc_back', 'selfie'], $this->provider_type === 'business' ? ['business_cert'] : []);
    }

    public function hasPayoutDetails(): bool
    {
        if (! $this->payout_method || ! $this->payout_account_name) {
            return false;
        }

        return $this->payout_method === 'bank_transfer'
            ? (bool) ($this->bank_name && $this->bank_account_number)
            : (bool) $this->payout_phone;
    }

    public function payoutLabel(): string
    {
        $name = Payment::METHODS[$this->payout_method] ?? null;
        if (! $name) {
            return 'Not set';
        }

        return $this->payout_method === 'bank_transfer'
            ? "{$name} – {$this->bank_name} •••• ".substr((string) $this->bank_account_number, -4)
            : "{$name} – {$this->payout_phone}";
    }

    /** What still stands between this provider and approval. Empty = ready to approve. */
    public function missingRequirements(): array
    {
        $missing = [];
        $docs = $this->documents()->get();
        if (! $this->nrc_number) {
            $missing[] = 'NRC number missing';
        }
        foreach ($this->requiredDocumentTypes() as $type) {
            $doc = $docs->firstWhere('type', $type);
            if (! $doc) {
                $missing[] = ProviderDocument::TYPES[$type].' not uploaded';
            } elseif ($doc->status !== 'approved') {
                $missing[] = ProviderDocument::TYPES[$type].($doc->status === 'rejected' ? ' was rejected' : ' not checked yet');
            }
        }
        foreach ($this->categories()->where('license_required', true)->get() as $cat) {
            $doc = $docs->first(fn ($d) => $d->type === 'licence' && $d->category_id === $cat->id);
            if (! $doc) {
                $missing[] = "Licence for {$cat->name} not uploaded";
            } elseif ($doc->status !== 'approved') {
                $missing[] = "Licence for {$cat->name}".($doc->status === 'rejected' ? ' was rejected' : ' not checked yet');
            } elseif ($doc->isExpired()) {
                $missing[] = "Licence for {$cat->name} has expired";
            }
        }
        if (! $this->hasPayoutDetails()) {
            $missing[] = 'Payment method details missing';
        }

        return $missing;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function displayName(): string
    {
        return $this->business_name ?: $this->user->name;
    }

    /** Best known position: live location if fresh, otherwise base location. */
    public function latitude(): ?float
    {
        return $this->current_latitude ?? $this->base_latitude;
    }

    public function longitude(): ?float
    {
        return $this->current_longitude ?? $this->base_longitude;
    }

    public function scopeAvailable($q)
    {
        return $q->where('verification_status', 'approved')
            ->where('is_online', true)
            // A provider whose approved licence has expired is not offered jobs until they renew it.
            ->whereDoesntHave('documents', fn ($d) => $d->where('status', 'approved')->whereNotNull('expires_at')->whereDate('expires_at', '<', now()->toDateString()))
            ->whereHas('user', fn ($u) => $u->where('status', 'active'));
    }
}
