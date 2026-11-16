<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\RequestWorkflow;
use Illuminate\Console\Command;
use Throwable;

class SyncPayments extends Command
{
    protected $signature = 'payments:sync';

    protected $description = 'Check pending MTN MoMo / Airtel Money prompts with the operator and settle them';

    public function handle(RequestWorkflow $wf): int
    {
        $pending = Payment::where('status', 'pending')->whereIn('gateway', ['mtn_momo', 'airtel_money'])
            ->where('created_at', '>=', now()->subDay())->get();
        foreach ($pending as $payment) {
            try {
                $this->line("Payment {$payment->id}: ".$wf->syncPayment($payment));
            } catch (Throwable $e) {
                $this->warn("Payment {$payment->id}: ".$e->getMessage());
            }
        }
        // Prompts nobody answered within 30 minutes are closed so the customer can try again.
        Payment::where('status', 'pending')->whereIn('gateway', ['mtn_momo', 'airtel_money'])->where('created_at', '<', now()->subMinutes(30))
            ->get()->each(function (Payment $p) {
                $p->update(['status' => 'failed', 'failure_reason' => 'Prompt expired']);
                if ($p->kind === 'job' && $p->serviceRequest->payment_status === 'pending') {
                    $p->serviceRequest->update(['payment_status' => 'unpaid']);
                }
            });

        return self::SUCCESS;
    }
}
