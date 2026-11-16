<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    public function index(Request $request, Ledger $ledger)
    {
        $show = $request->query('show', 'all');
        $min = (float) Setting::get('min_payout', 100);

        $providers = User::where('role', 'provider')->with('providerProfile')->has('ledgerEntries')
            ->withSum('ledgerEntries as balance', 'amount')
            ->withSum(['ledgerEntries as earned' => fn ($q) => $q->where('type', 'earning')], 'amount')
            ->withSum(['ledgerEntries as commission_owed' => fn ($q) => $q->where('type', 'commission_due')], 'amount')
            ->withSum(['ledgerEntries as sent' => fn ($q) => $q->whereIn('type', ['payout', 'payout_reversal'])], 'amount')
            ->get()
            ->map(function (User $u) {
                $u->balance = round((float) $u->balance, 2);

                return $u;
            })
            ->filter(fn (User $u) => match ($show) {
                'due' => $u->balance >= $min && $u->balance > 0,
                'owing' => $u->balance < 0,
                default => true,
            })
            ->sortByDesc(fn (User $u) => abs($u->balance))->values();

        return view('admin.payouts.index', [
            'providers' => $providers,
            'show' => $show,
            'summary' => $ledger->summary(),
            'pending' => Payout::with('provider')->where('status', 'pending')->latest()->get(),
            'recent' => Payout::with('provider')->whereIn('status', ['paid', 'cancelled'])->latest()->take(15)->get(),
        ]);
    }

    public function provider(User $user, Ledger $ledger)
    {
        abort_unless($user->isProvider(), 404);
        $running = 0.0;
        $entries = LedgerEntry::where('provider_id', $user->id)->orderBy('id')->get()->map(function ($e) use (&$running) {
            $running = round($running + (float) $e->amount, 2);
            $e->running = $running;

            return $e;
        })->reverse()->take(100)->values();

        return view('admin.payouts.provider', [
            'provider' => $user->load('providerProfile'),
            'profile' => $user->providerProfile,
            'balance' => $ledger->balance($user),
            'entries' => $entries,
            'payouts' => $user->payouts()->latest()->take(20)->get(),
            'min' => (float) Setting::get('min_payout', 100),
        ]);
    }

    public function create(Request $request, User $user, Ledger $ledger)
    {
        abort_unless($user->isProvider(), 404);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'note' => 'nullable|string|max:200']);
        $payout = $ledger->createPayout($user, (float) $data['amount'], $request->user(), $data['note'] ?? null);

        return redirect()->route('admin.payouts.index')->with('success', "Payout {$payout->reference} of ".zmw($payout->amount)." created for {$user->name}. Send it to {$payout->destination}, then mark it paid.");
    }

    public function markPaid(Request $request, Payout $payout, Ledger $ledger)
    {
        $data = $request->validate(['transaction_reference' => 'required|string|max:100'], ['transaction_reference.required' => 'Enter the mobile money or bank transaction reference.']);
        $ledger->markPaid($payout, trim($data['transaction_reference']), $request->user());

        return back()->with('success', "Payout {$payout->reference} marked as paid. {$payout->provider->name} has been notified.");
    }

    public function cancel(Request $request, Payout $payout, Ledger $ledger)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $ledger->cancelPayout($payout, $data['reason'], $request->user());

        return back()->with('success', "Payout {$payout->reference} cancelled. The amount is back on the provider's balance.");
    }

    public function commission(Request $request, User $user, Ledger $ledger)
    {
        abort_unless($user->isProvider(), 404);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'method' => 'nullable|string|max:40', 'reference' => 'nullable|string|max:100']);
        $ledger->receiveCommission($user, (float) $data['amount'], $data['method'] ?? null, $data['reference'] ?? null, $request->user());

        return back()->with('success', 'Commission of '.zmw($data['amount']).' recorded.');
    }

    public function adjust(Request $request, User $user, Ledger $ledger)
    {
        abort_unless($user->isProvider(), 404);
        $data = $request->validate(['amount' => 'required|numeric|between:-10000000,10000000', 'reason' => 'required|string|max:200']);
        $ledger->adjust($user, (float) $data['amount'], $data['reason'], $request->user());

        return back()->with('success', 'Adjustment recorded.');
    }

    public function export(): StreamedResponse
    {
        $rows = Payout::with('provider')->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Provider', 'Amount (ZMW)', 'Paid to', 'Account name', 'Status', 'Transaction ref', 'Created', 'Paid']);
            foreach ($rows as $p) {
                fputcsv($out, [$p->reference, $p->provider?->name, $p->amount, $p->destination, $p->account_name, $p->status, $p->transaction_reference,
                    $p->created_at->format('Y-m-d H:i'), $p->paid_at?->format('Y-m-d H:i')]);
            }
            fclose($out);
        }, 'handynow-payouts-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
