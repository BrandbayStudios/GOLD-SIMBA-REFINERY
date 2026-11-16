{{-- $label, $doc (ProviderDocument|null), $type, $categoryId, $hint?, $removable? --}}
<li class="py-3" x-data="{ open: {{ $doc && $doc->status !== 'rejected' ? 'false' : 'true' }} }">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-bold">{{ $label }}</p>
            @if($doc)
                <p class="truncate text-xs text-navy-500">{{ $doc->original_name }}@if($doc->number) · No. {{ $doc->number }}@endif</p>
                @if($doc->expires_at)<p class="text-xs {{ $doc->isExpired() ? 'font-bold text-red-600' : ($doc->expiresSoon() ? 'font-semibold text-amber-600' : 'text-navy-500') }}">{{ $doc->isExpired() ? 'EXPIRED ' : ($doc->expiresSoon() ? 'Expires soon: ' : 'Expires ') }}{{ $doc->expires_at->format('d M Y') }}</p>@endif
                @if($doc->review_note)<p class="mt-1 rounded-lg bg-red-50 p-2 text-xs text-red-800">{{ $doc->review_note }}</p>@endif
            @elseif(! empty($hint))
                <p class="text-xs text-navy-500">{{ $hint }}</p>
            @endif
        </div>
        <div class="flex shrink-0 flex-col items-end gap-1">
            @if($doc)<x-status-badge :status="$doc->status" :label="ucfirst($doc->status)" />@else<span class="badge badge-red">Missing</span>@endif
            <div class="flex gap-2 text-xs font-semibold">
                @if($doc)<a href="{{ route('provider.documents.view', $doc) }}" target="_blank" class="text-brand-600">View</a>@endif
                <button type="button" @click="open = !open" class="text-navy-500 underline">{{ $doc ? 'Replace' : 'Upload' }}</button>
                @if($doc && ! empty($removable))
                    <form method="POST" action="{{ route('provider.documents.destroy', $doc) }}" class="inline" onsubmit="return confirm('Remove this document?')">@csrf @method('DELETE')<button class="text-red-600 underline">Remove</button></form>
                @endif
            </div>
        </div>
    </div>
    <form x-show="open" x-cloak method="POST" action="{{ route('provider.documents.store') }}" enctype="multipart/form-data" class="mt-3 space-y-2 rounded-xl bg-mist p-3">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        @if($categoryId)<input type="hidden" name="category_id" value="{{ $categoryId }}">@endif
        @if($type === 'licence')
            <div class="grid grid-cols-2 gap-2"><x-field name="number" label="Licence number" /><x-field name="expires_at" type="date" label="Expiry date" /></div>
        @endif
        <x-field name="file" type="file" accept="image/*,.pdf" />
        <button class="btn btn-navy btn-sm">Upload</button>
    </form>
</li>
