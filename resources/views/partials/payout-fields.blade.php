{{-- Payment method a provider wants to be paid on. $p = ProviderProfile|null (existing values) --}}
@php $v = fn ($k, $d = null) => old($k, $p?->{$k} ?? $d); @endphp
<div x-data="{ payout: '{{ $v('payout_method', 'mtn_momo') }}' }" class="space-y-4">
    <div>
        <p class="label">How do you want to be paid? <span class="text-brand-600">*</span></p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach(['mtn_momo' => 'MTN MoMo', 'airtel_money' => 'Airtel Money', 'zamtel_kwacha' => 'Zamtel Kwacha', 'bank_transfer' => 'Bank account'] as $k => $label)
                <label class="cursor-pointer rounded-xl border-2 p-3 text-center text-sm font-bold" :class="payout === '{{ $k }}' ? 'border-brand-500 bg-brand-50' : 'border-navy-50'">
                    <input type="radio" name="payout_method" value="{{ $k }}" x-model="payout" class="sr-only"> {{ $label }}
                </label>
            @endforeach
        </div>
        @error('payout_method')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div x-show="payout !== 'bank_transfer'"><x-field name="payout_phone" type="tel" label="Mobile money number" :value="$p?->payout_phone" placeholder="097 123 4567" /></div>
        <x-field name="payout_account_name" label="Name on the wallet / account" :value="$p?->payout_account_name" :required="true" />
        <template x-if="payout === 'bank_transfer'">
            <div class="contents">
                <div>
                    <label class="label" for="bank_name">Bank <span class="text-brand-600">*</span></label>
                    <select id="bank_name" name="bank_name" class="input @error('bank_name') input-error @enderror">
                        <option value="">Choose your bank…</option>
                        @foreach(\App\Models\ProviderProfile::BANKS as $bank)<option @selected($v('bank_name') === $bank)>{{ $bank }}</option>@endforeach
                    </select>
                    @error('bank_name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-field name="bank_account_number" label="Account number" :value="$p?->bank_account_number" inputmode="numeric" />
                <x-field name="bank_branch" label="Branch (optional)" :value="$p?->bank_branch" />
            </div>
        </template>
    </div>
    <p class="text-xs text-navy-500">Your earnings are paid to this account. Changes are checked by our team and flagged for your safety.</p>
</div>
