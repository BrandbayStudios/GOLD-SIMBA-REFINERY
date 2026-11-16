<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

trait ValidatesNrc
{
    /** Validation rule for an NRC typed as 123456/78/1 (slashes optional). */
    protected function nrcRule(): array
    {
        return ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
            if (! normalize_nrc($value)) {
                $fail('Enter your NRC number like 123456/78/1.');
            }
        }];
    }

    /** Returns the normalised NRC, or fails when another account already uses it. */
    protected function uniqueNrc(string $input, ?int $ignoreUserId = null): string
    {
        $nrc = normalize_nrc($input);
        $taken = User::where('nrc_number', $nrc)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['nrc_number' => 'An account with this NRC already exists. Log in, or contact support if this is a mistake.']);
        }

        return $nrc;
    }
}
