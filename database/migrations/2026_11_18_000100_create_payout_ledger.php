<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->nullable();          // snapshot of how the provider asked to be paid
            $table->string('destination')->nullable();         // e.g. "MTN Mobile Money – 260966…"
            $table->string('account_name', 120)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | paid | cancelled
            $table->string('transaction_reference', 100)->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        // One signed amount per line: positive = HANDY NOW owes the provider more, negative = owes less.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index(); // earning | commission_due | payout | payout_reversal | commission_paid | adjustment
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'type']); // a payment is booked once
            $table->index(['provider_id', 'created_at']);
        });

        // Book payments that were confirmed before the ledger existed.
        foreach (DB::table('payments')->where('status', 'confirmed')->whereNotNull('provider_id')->get() as $p) {
            $platformCollected = in_array($p->gateway, ['mtn_momo', 'airtel_money'], true);
            $amount = $platformCollected ? (float) $p->provider_amount : -(float) $p->commission_amount;
            if ($amount == 0.0) {
                continue;
            }
            $ref = DB::table('service_requests')->where('id', $p->service_request_id)->value('reference');
            DB::table('ledger_entries')->insert([
                'provider_id' => $p->provider_id, 'payment_id' => $p->id, 'type' => $platformCollected ? 'earning' : 'commission_due', 'amount' => $amount,
                'description' => $platformCollected ? "Job {$ref} – paid through HANDY NOW (after commission)" : "Commission on job {$ref} – customer paid you directly",
                'created_at' => $p->confirmed_at ?? now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
    }
};
