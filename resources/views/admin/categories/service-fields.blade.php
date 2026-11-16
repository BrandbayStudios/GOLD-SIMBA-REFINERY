{{-- Pricing fields for one service. $s = Service|null (null = new service). Wrapped by the caller's <form>. --}}
@php $v = fn ($k, $d = null) => old($k, $s?->{$k} ?? $d); @endphp
<div x-data="{ mode: '{{ $v('pricing_mode', 'open') }}', dest: {{ $v('needs_destination') ? 'true' : 'false' }} }" class="space-y-3">
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field name="name" label="Service name" :value="$s?->name" :required="true" />
        <div>
            <label class="label">How is it priced?</label>
            <select name="pricing_mode" x-model="mode" class="input">
                <option value="open">Open quotes – providers quote any amount</option>
                <option value="capped">Price ceiling – quotes up to a maximum</option>
                <option value="fixed">Fixed price – no quotes, first provider to accept</option>
            </select>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
        <x-field name="min_price" type="number" step="0.01" label="“Starting at” (K)" :value="$s?->min_price" hint="Shown to customers" />
        <div x-show="mode === 'capped'"><x-field name="max_price" type="number" step="0.01" label="Ceiling (K, total)" :value="$s?->max_price" hint="Work + call-out" /></div>
        <div x-show="mode !== 'capped'"><x-field name="max_price" type="number" step="0.01" label="Typical maximum (K)" :value="$s?->max_price" hint="Internal reference only" /></div>
        <x-field name="callout_fee" type="number" step="0.01" label="Call-out (K)" :value="$s?->callout_fee" hint="Pre-fills provider quotes" />
    </div>
    <div x-show="mode === 'fixed'" x-cloak class="rounded-xl bg-emerald-50 p-3">
        <div class="grid gap-3 sm:grid-cols-3">
            <x-field name="fixed_price" type="number" step="0.01" label="Fixed price (K, all-in)" :value="$s?->fixed_price" />
            <label class="flex items-center gap-2 self-end pb-3 text-sm font-semibold sm:col-span-2"><input type="checkbox" name="needs_destination" value="1" x-model="dest" class="accent-brand-500"> Customer must give a drop-off point (e.g. towing)</label>
        </div>
        <div x-show="dest" x-cloak class="mt-2 grid gap-3 sm:grid-cols-3">
            <x-field name="price_per_km" type="number" step="0.01" label="Extra per km (K)" :value="$s?->price_per_km" />
            <x-field name="included_km" type="number" step="0.1" label="Km included in fixed price" :value="$s?->included_km ?? 0" />
            <p class="self-end pb-2 text-xs text-emerald-900">Km is the estimated road distance (straight line × 1.3) from the customer to the drop-off.</p>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field name="booking_fee" type="number" step="0.01" label="Booking fee for this service (K)" :value="$s?->booking_fee" hint="Leave blank to use the platform default. 0 = no fee." />
        <x-field name="description" label="Description" :value="$s?->description" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($s ? $s->is_active : true) class="accent-brand-500"> Active</label>
</div>
