<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each service is priced. 'open' keeps today's behaviour (providers quote freely).
        Schema::table('services', function (Blueprint $table) {
            $table->string('pricing_mode', 10)->default('open')->after('max_price'); // open | capped | fixed
            $table->decimal('fixed_price', 10, 2)->nullable()->after('pricing_mode');  // all-in price for a fixed job
            $table->decimal('price_per_km', 8, 2)->nullable()->after('fixed_price');   // extra per km beyond included_km (towing)
            $table->decimal('included_km', 6, 1)->default(0)->after('price_per_km');
            $table->boolean('needs_destination')->default(false)->after('included_km'); // customer must give a drop-off point
            $table->decimal('booking_fee', 8, 2)->nullable()->after('needs_destination'); // overrides the global booking fee
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('dest_address')->nullable()->after('directions');
            $table->decimal('dest_latitude', 10, 7)->nullable()->after('dest_address');
            $table->decimal('dest_longitude', 10, 7)->nullable()->after('dest_latitude');
            $table->decimal('route_km', 7, 1)->nullable()->after('dest_longitude');
            $table->decimal('fixed_price', 10, 2)->nullable()->after('route_km');
            $table->decimal('booking_fee_amount', 8, 2)->default(0)->after('provider_earnings');
            $table->string('booking_fee_status', 12)->default('none')->index()->after('booking_fee_amount'); // none|pending|paid|waived|refund_due|refunded
            $table->timestamp('booking_fee_paid_at')->nullable()->after('booking_fee_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind', 12)->default('job')->index()->after('service_request_id'); // job | booking_fee
        });

        Schema::create('price_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('reason', 255);
            $table->string('status', 10)->default('pending')->index(); // pending | approved | declined
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_variations');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('service_requests', fn (Blueprint $t) => $t->dropColumn(['dest_address', 'dest_latitude', 'dest_longitude', 'route_km', 'fixed_price', 'booking_fee_amount', 'booking_fee_status', 'booking_fee_paid_at']));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['pricing_mode', 'fixed_price', 'price_per_km', 'included_km', 'needs_destination', 'booking_fee']));
    }
};
