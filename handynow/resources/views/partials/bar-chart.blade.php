{{-- $data: list of ['date' => label, $key => value]; $key; $format callable|null --}}
@php $max = max(1, collect($data)->max($key)); $fmt = $format ?? fn ($v) => $v; @endphp
<div class="flex h-48 items-end gap-1" role="img" aria-label="{{ $label ?? 'Chart' }}">
    @foreach($data as $d)
        <div class="group relative flex h-full flex-1 flex-col justify-end">
            <div class="w-full rounded-t-md {{ $color ?? 'bg-brand-500' }} transition group-hover:opacity-80" style="height: {{ max(2, $d[$key] / $max * 100) }}%"></div>
            <span class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-navy-900 px-2 py-1 text-[10px] font-bold text-white group-hover:block">{{ $d['date'] }}: {{ $fmt($d[$key]) }}</span>
        </div>
    @endforeach
</div>
<div class="mt-2 flex justify-between text-[10px] font-semibold text-navy-300"><span>{{ $data[0]['date'] ?? '' }}</span><span>{{ end($data)['date'] ?? '' }}</span></div>
