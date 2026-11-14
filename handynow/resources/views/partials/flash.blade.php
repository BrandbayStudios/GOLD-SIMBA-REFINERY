@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
        <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
        <span class="flex-1">{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900" aria-label="Dismiss"><x-icon name="x" class="h-4 w-4" /></button>
    </div>
@endif
@if($errors->any())
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
        <div>
            <p class="font-semibold">Please check the form:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
@endif
