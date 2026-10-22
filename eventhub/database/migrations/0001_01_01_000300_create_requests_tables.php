<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event planning requests (the "Plan Event" modal -> Firestore `events`)
        Schema::create('event_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('event_type')->nullable();
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('guest_count')->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });

        // Quote requests to a specific vendor (the "Quote" modal -> Firestore `eventRequests`)
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_name')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('event_type')->nullable();
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('guest_count')->default(0);
            $table->text('requirements')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
        Schema::dropIfExists('event_plans');
    }
};
