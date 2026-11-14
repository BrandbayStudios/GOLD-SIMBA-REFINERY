@props(['status', 'label' => null])
<span {{ $attributes->merge(['class' => 'badge '.status_badge_class($status)]) }}>{{ $label ?? (\App\Models\ServiceRequest::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status))) }}</span>
