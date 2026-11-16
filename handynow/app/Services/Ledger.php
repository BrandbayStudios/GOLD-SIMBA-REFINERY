<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Running balance between HANDY NOW and each provider.
 *   +  HANDY NOW owes the provider  (customer paid through the app; net of commission)
 *   –  the provider owes HANDY NOW  (customer paid the provider directly; commission is due)
 * Payouts and commission payments move the balance back toward zero.
 */
class Ledger
{
    public function __construct(private Notifier $notify) {}

    public function balance(User|int $provider): float
    {
        return round((float) LedgerEntry::where('provider_id', $provider instanceof User ? $provider->id : $provider)->sum('amount'), 2);
    }

    /** Books a confirmed payment exactly once. */
    public function recordPayment(Payment $payment): ?LedgerEntry
    {
        if (($payment->kind ?: 'job') !== 'job' || $payment->status !== 'confirmed' || ! $payment->provider_id) {
            return null;
        }
        $ref = $payment->serviceRequest->reference;
        if ($payment->isOnline()) {
            $type = 'earning';
            $amount = (float) $payment->provider_amount;
            $text = "Job {$ref} – paid through HANDY NOW via {$payment->methodLabel()} (after commission)";
        } else {
            $type = 'commission_due';
            $amount = -(float) $payment->commission_amount;
            $text = "Commission on job {$ref} – customer paid you directly ({$payment->methodLabel()})";
        }
        if (round($amount, 2) == 0.0) {
            return null;
        }

        return LedgerEntry::firstOrCreate(
            ['payment_id' => $payment->id, 'type' => $type],
            ['provider_id' => $payment->provider_id, 'amount' => round($amount, 2), 'description' => $text],
        );
    }

    /** Reserves money for a payout. The admin then sends it and marks the payout paid. */
    public function createPayout(User $provider, float $amount, User $admin, ?string $note = null): Payout
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($provider, $amount, $admin, $note) {
            User::whereKey($provider->id)->lockForUpdate()->first();
            $profile = $provider->providerProfile;
            if (! $profile || ! $profile->hasPayoutDetails()) {
                throw ValidationException::withMessages(['amount' => 'This provider has not set up a payment method yet.']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
            }
            $balance = $this->balance($provider);
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'That is more than the '.zmw(max($balance, 0)).' HANDY NOW owes this provider.']);
            }

            $payout = Payout::create([
                'provider_id' => $provider->id, 'amount' => $amount, 'method' => $profile->payout_method, 'destination' => $profile->payoutLabel(),
                'account_name' => $profile->payout_account_name, 'status' => 'pending', 'note' => $note, 'created_by' => $admin->id,
            ]);
            LedgerEntry::create([
                'provider_id' => $provider->id, 'payout_id' => $payout->id, 'type' => 'payout', 'amount' => -$amount,
                'description' => "Payout {$payout->reference} to {$profile->payoutLabel()}", 'created_by' => $admin->id,
            ]);

            return $payout;
        });
    }

    public function markPaid(Payout $payout, string $transactionReference, User $admin): void
    {
        DB::transaction(function () use ($payout, $transactionReference, $admin) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->first();
            if ($payout->status !== 'pending') {
                throw ValidationException::withMessages(['payout' => 'This payout is already '.$payout->status.'.']);
            }
            $payout->update(['status' => 'paid', 'transaction_reference' => $transactionReference, 'paid_by' => $admin->id, 'paid_at' => now()]);
        });

        $payout->refresh();
        $this->notify->user($payout->provider, 'Payout sent',
            'You were paid '.zmw($payout->amount)." to {$payout->destination} (ref {$payout->transaction_reference}).",
            route('provider.earnings'), 'payment', true);
    }

    public function cancelPayout(Payout $payout, string $reason, User $admin): void
    {
        DB::transaction(function () use ($payout, $reason, $admin) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->first();
            if ($payout->status !== 'pending') {
                throw ValidationException::withMessages(['payout' => 'Only pending payouts can be cancelled.']);
            }
            $payout->update(['status' => 'cancelled', 'cancelled_at' => now(), 'note' => mb_substr(trim(($payout->note ? $payout->note.' | ' : '').'Cancelled: '.$reason), 0, 300)]);
            LedgerEntry::create([
                'provider_id' => $payout->provider_id, 'payout_id' => $payout->id, 'type' => 'payout_reversal', 'amount' => $payout->amount,
                'description' => "Payout {$payout->reference} cancelled: {$reason}", 'created_by' => $admin->id,
            ]);
        });
    }

    /** The provider paid commission they owed (on jobs the customer paid them for directly). */
    public function receiveCommission(User $provider, float $amount, ?string $method, ?string $reference, User $admin): LedgerEntry
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($provider, $amount, $method, $reference, $admin) {
            User::whereKey($provider->id)->lockForUpdate()->first();
            $owed = -$this->balance($provider);
            if ($owed <= 0) {
                throw ValidationException::withMessages(['amount' => 'This provider does not owe HANDY NOW anything.']);
            }
            if ($amount <= 0 || $amount > $owed) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount between K0.01 and '.zmw($owed).' (what they owe).']);
            }

            return LedgerEntry::create([
                'provider_id' => $provider->id, 'type' => 'commission_paid', 'amount' => $amount, 'created_by' => $admin->id,
                'description' => 'Commission received'.($method ? " via {$method}" : '').($reference ? " (ref {$reference})" : ''),
            ]);
        });
    }

    /** Manual correction (refund, dispute outcome, mistake). Positive = HANDY NOW owes more; negative = owes less. */
    public function adjust(User $provider, float $amount, string $reason, User $admin): LedgerEntry
    {
        if (round($amount, 2) == 0.0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount other than zero.']);
        }

        return LedgerEntry::create([
            'provider_id' => $provider->id, 'type' => 'adjustment', 'amount' => round($amount, 2),
            'description' => 'Adjustment: '.$reason, 'created_by' => $admin->id,
        ]);
    }

    /** Totals for the admin dashboard and payouts page. */
    public function summary(): array
    {
        $balances = LedgerEntry::selectRaw('provider_id, SUM(amount) as bal')->groupBy('provider_id')->pluck('bal', 'provider_id')->map(fn ($b) => round((float) $b, 2));
        $min = (float) Setting::get('min_payout', 100);

        return [
            'owed_to_providers' => round($balances->filter(fn ($b) => $b > 0)->sum(), 2),
            'owed_by_providers' => round(abs($balances->filter(fn ($b) => $b < 0)->sum()), 2),
            'due_now_count' => $balances->filter(fn ($b) => $b >= $min && $b > 0)->count(),
            'due_now_amount' => round($balances->filter(fn ($b) => $b >= $min && $b > 0)->sum(), 2),
            'pending_payouts' => round((float) Payout::where('status', 'pending')->sum('amount'), 2),
            'paid_this_month' => round((float) Payout::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'), 2),
            'min_payout' => $min,
        ];
    }
}
