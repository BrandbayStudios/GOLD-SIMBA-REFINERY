<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_reference', 64)->nullable()->index()->after('gateway');
            $table->string('gateway_status', 40)->nullable()->after('gateway_reference');
            $table->string('failure_reason', 255)->nullable()->after('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_reference', 'gateway_status', 'failure_reason']);
        });
    }
};
