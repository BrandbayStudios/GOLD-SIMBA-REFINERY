@extends('layouts.dashboard')

@section('title', 'My Dashboard')

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
    <div class="dash-eyebrow">Customer</div>
    <h1>Welcome back, {{ $user->name }}.</h1>
    <p>Track the quotes you've requested and the events you're planning.</p>
</div>

<div class="stat-grid">
    <div class="stat-card accent">
        <div class="stat-value">{{ $quotes->count() }}</div>
        <div class="stat-label">Quote requests</div>
    </div>
    <div class="stat-card gold">
        <div class="stat-value">{{ $plans->count() }}</div>
        <div class="stat-label">Events planned</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $quotes->where('status', 'new')->count() }}</div>
        <div class="stat-label">Awaiting response</div>
    </div>
</div>

{{-- QUOTE REQUESTS --}}
<div class="panel">
    <div class="panel-head">
        <h2>My Quote Requests</h2>
        <span class="count-pill">{{ $quotes->count() }} total</span>
    </div>

    @if ($quotes->isEmpty())
        <div class="empty">
            <i class="ri-file-list-3-line"></i>
            You haven't requested any quotes yet.
            <div><a href="{{ route('home') }}#vendors">Browse vendors →</a></div>
        </div>
    @else
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Vendor</th><th>Event</th><th>Date</th><th>Guests</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotes as $quote)
                    <tr>
                        <td>
                            <strong>{{ $quote->vendor_name ?? $quote->vendor?->name ?? '—' }}</strong>
                            @if ($quote->requirements)
                                <div class="cell-sub">{{ \Illuminate\Support\Str::limit($quote->requirements, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $quote->event_type ?? '—' }}</td>
                        <td>{{ $quote->event_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $quote->guest_count ?: '—' }}</td>
                        <td><span class="badge {{ $badge($quote->status) }}">{{ $quote->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- EVENT PLANS --}}
<div class="panel">
    <div class="panel-head">
        <h2>My Event Plans</h2>
        <span class="count-pill">{{ $plans->count() }} total</span>
    </div>

    @if ($plans->isEmpty())
        <div class="empty">
            <i class="ri-calendar-event-line"></i>
            No event plans yet.
            <div><a href="{{ route('home') }}">Plan an event →</a></div>
        </div>
    @else
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Event</th><th>Date</th><th>Location</th><th>Guests</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>
                            <strong>{{ $plan->event_type ?? 'Event' }}</strong>
                            @if ($plan->notes)
                                <div class="cell-sub">{{ \Illuminate\Support\Str::limit($plan->notes, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $plan->event_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $plan->location ?? '—' }}</td>
                        <td>{{ $plan->guest_count ?: '—' }}</td>
                        <td><span class="badge {{ $badge($plan->status) }}">{{ $plan->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
