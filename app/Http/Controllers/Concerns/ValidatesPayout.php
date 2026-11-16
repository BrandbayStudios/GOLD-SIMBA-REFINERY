<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\ProviderProfile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** How a provider wants to be paid: a mobile money wallet or a bank account. */
trait ValidatesPayout
{
    protected function payoutRules(): array
    {
        $mobile = ['mtn_momo', 'airtel_money', 'zamtel_kwacha'];

        return [
            'payout_method' => ['required', Rule::in(array_merge($mobile, ['bank_transfer']))],
            'payout_phone' => ['nullable', 'required_if:payout_method,'.implode(',', $mobile), 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'payout_account_name' => 'required|string|max:120',
            'bank_name' => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:80'],
            'bank_account_number' => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:30', 'regex:/^[0-9 -]{6,30}$/'],
            'bank_branch' => 'nullable|string|max:80',
        ];
    }

    protected function payoutMessages(): array
    {
        return [
            'payout_phone.required_if' => 'Enter the mobile money number you want to be paid on.',
            'payout_account_name.required' => 'Enter the name registered on the wallet or bank account.',
            'bank_name.required_if' => 'Choose your bank.',
            'bank_account_number.required_if' => 'Enter your bank account number.',
            'bank_account_number.regex' => 'Bank account numbers contain digits only.',
        ];
    }

    /** Returns the columns to store, throwing a validation error when the number belongs to another network. */
    protected function payoutAttributes(array $data): array
    {
        $method = $data['payout_method'];
        $isBank = $method === 'bank_transfer';
        if (! $isBank) {
            $prefix = substr(zm_phone($data['payout_phone']), 3, 2);
            if (! in_array($prefix, Payment::NETWORK_PREFIXES[$method], true)) {
                throw ValidationException::withMessages(['payout_phone' => 'That number does not look like a '.Payment::METHODS[$method].' number (expected 0'.implode('/0', Payment::NETWORK_PREFIXES[$method]).'…).']);
            }
        }

        return [
            'payout_method' => $method,
            'payout_phone' => $isBank ? null : zm_phone($data['payout_phone']),
            'payout_account_name' => trim($data['payout_account_name']),
            'bank_name' => $isBank ? $data['bank_name'] : null,
            'bank_account_number' => $isBank ? preg_replace('/[^0-9]/', '', $data['bank_account_number']) : null,
            'bank_branch' => $isBank ? ($data['bank_branch'] ?? null) : null,
        ];
    }

    protected function payoutChanged(ProviderProfile $profile, array $new): bool
    {
        foreach ($new as $k => $v) {
            if ((string) $profile->{$k} !== (string) $v) {
                return true;
            }
        }

        return false;
    }
}
