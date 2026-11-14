@props(['label', 'value', 'icon' => 'chart', 'tone' => 'navy', 'href' => null, 'hint' => null])
@php $tones = ['navy' => 'bg-navy-50 text-navy-900', 'orange' => 'bg-brand-50 text-brand-600', 'green' => 'bg-emerald-50 text-emerald-600', 'red' => 'bg-red-50 text-red-600', 'violet' => 'bg-violet-50 text-violet-600']; @endphp
@php $inner = '' @endphp
@if($href)<a href="{{ $href }}" class="card card-pad flex items-start gap-4 transition hover:-translate-y-0.5 hover:shadow-lg">@else<div class="card card-pad flex items-start gap-4">@endif
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['navy'] }}"><x-icon :name="$icon" /></span>
    <span class="min-w-0">
        <span class="block text-xs font-semibold uppercase tracking-wider text-navy-300">{{ $label }}</span>
        <span class="mt-0.5 block font-display text-2xl font-bold text-navy-900">{{ $value }}</span>
        @if($hint)<span class="block text-xs text-navy-300">{{ $hint }}</span>@endif
    </span>
@if($href)</a>@else</div>@endif
