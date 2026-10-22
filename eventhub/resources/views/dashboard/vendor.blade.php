@extends('layouts.dashboard')

@section('title', 'Vendor Dashboard')

@php
    $badge = fn ($status) => match ($status) {
        'new' => 'badge-new',
        'approved' => 'badge-approved',
        'rejected' => 'badge-rejected',
        default => 'badge-pending',
    };
@endphp

@section('content')

<div class="dash-head">
    <div class="dash-eyebrow">Vendor</div>
    <h1>{{ $vendor->name }}</h1>
    <p>Manage your listing, incoming quote requests and customer reviews.</p>
</div>

{{-- PROFILE SUMMARY --}}
<div class="panel">
    <div class="vendor-summary">
        <img src="{{ $vendor->logo ?? $vendor->cover }}" alt="{{ $vendor->name }}">
        <div style="flex:1;min-width:200px;">
            <div class="vs-name">{{ $vendor->name }}</div>
            <div class="vs-meta">{{ $vendor->category }} · {{ $vendor->location }}, Zambia</div>
            <div class="vs-meta">
                <span class="vs-rating"><i class="ri-star-fill"></i> {{ $vendor->ratingLabel() }}</span>
                · {{ $vendor->reviews_count }} reviews
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span class="badge {{ $badge($vendor->status) }}">{{ $vendor->status }}</span>
            @if ($vendor->verified)
                <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
            @else
                <span class="badge badge-muted">Unverified</span>
            @endif
        </div>
    </div>
    @if ($vendor->status === 'pending')
        <p class="cell-sub" style="margin-top:16px;">
            <i class="ri-time-line"></i> Your listing is under review. It will appear in the marketplace once approved.
        </p>
    @endif
</div>

<div class="stat-grid">
    <div class="stat-card accent">
        <div class="stat-value">{{ $quotes->count() }}</div>
        <div class="stat-label">Quote requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $quotes->where('status', 'new')->count() }}</div>
        <div class="stat-label">New / unanswered</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-value">{{ $vendor->ratingLabel() }}</div>
        <div class="stat-label">Average rating</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $reviews->count() }}</div>
        <div class="stat-label">Reviews on file</div>
    </div>
</div>

{{-- INCOMING QUOTES --}}
<div class="panel">
    <div class="panel-head">
        <h2>Incoming Quote Requests</h2>
        <span class="count-pill">{{ $quotes->count() }} total</span>
    </div>

    @if ($quotes->isEmpty())
        <div class="empty">
            <i class="ri-inbox-line"></i>
            No quote requests yet. They'll appear here as customers reach out.
        </div>
    @else
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Customer</th><th>Event</th><th>Date</th><th>Guests</th><th>Contact</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotes as $quote)
                    <tr>
                        <td>
                            <strong>{{ $quote->name }}</strong>
                            @if ($quote->requirements)
                                <div class="cell-sub">{{ \Illuminate\Support\Str::limit($quote->requirements, 70) }}</div>
                            @endif
                        </td>
                        <td>{{ $quote->event_type ?? '—' }}</td>
                        <td>{{ $quote->event_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $quote->guest_count ?: '—' }}</td>
                        <td class="cell-sub">{{ $quote->phone ?? '—' }}</td>
                        <td><span class="badge {{ $badge($quote->status) }}">{{ $quote->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- REVIEWS --}}
<div class="panel">
    <div class="panel-head">
        <h2>Customer Reviews</h2>
        <span class="count-pill">{{ $reviews->count() }} shown</span>
    </div>

    @if ($reviews->isEmpty())
        <div class="empty">
            <i class="ri-chat-smile-3-line"></i>
            No reviews yet.
        </div>
    @else
        @foreach ($reviews as $review)
            <div class="dash-review">
                <div class="dash-review-head">
                    <span class="name">{{ $review->author }}</span>
                    <span class="stars">{{ $review->starsLabel() }}</span>
                </div>
                <p>{{ $review->body }}</p>
            </div>
        @endforeach
    @endif
</div>

@endsection
