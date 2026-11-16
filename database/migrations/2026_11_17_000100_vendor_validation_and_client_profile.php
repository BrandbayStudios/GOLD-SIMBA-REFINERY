<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clients: NRC + pinned address; email becomes optional (phone is the main login).
        Schema::table('users', function (Blueprint $table) {
            $table->string('nrc_number', 20)->nullable()->index()->after('phone');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Providers: how they get paid.
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('payout_method', 20)->nullable()->after('verification_notes'); // mtn_momo | airtel_money | zamtel_kwacha | bank_transfer
            $table->string('payout_phone', 20)->nullable()->after('payout_method');
            $table->string('payout_account_name', 120)->nullable()->after('payout_phone');
            $table->string('bank_name', 80)->nullable()->after('payout_account_name');
            $table->string('bank_account_number', 30)->nullable()->after('bank_name');
            $table->string('bank_branch', 80)->nullable()->after('bank_account_number');
        });

        // Which services need a licence, and what to upload for it (editable by admins).
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('license_required')->default(false)->after('is_emergency');
            $table->string('license_hint', 255)->nullable()->after('license_required');
        });

        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // nrc_front | nrc_back | selfie | business_cert | licence | certificate | other
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // the service a licence is for
            $table->string('title', 120)->nullable();
            $table->string('number', 60)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('path');
            $table->string('original_name', 150)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Suggested licence rules (examples only – admins can change them under Services & pricing).
        $rules = [
            'roadside-assistance' => [true, "Valid driver's licence (plus tow-truck or vehicle documents if you tow)."],
            'electrical-services' => [true, "Electrical wireman's licence or trade test certificate (for example from the Energy Regulation Board or TEVETA)."],
            'plumbing-services' => [true, 'Plumbing trade test certificate or qualification (for example TEVETA).'],
            'gas-services' => [true, 'LPG handling or installation permit, or a gas safety training certificate.'],
            'mechanical-services' => [true, 'Motor mechanic trade test certificate or qualification.'],
            'carpentry' => [false, 'Carpentry trade certificate, if you have one.'],
            'welding-metalwork' => [false, 'Welding or fabrication trade certificate, if you have one.'],
            'additional-services' => [false, 'Any trade certificate or licence for your service (e.g. locksmith, refrigeration, pest control).'],
        ];
        foreach ($rules as $slug => [$required, $hint]) {
            DB::table('categories')->where('slug', $slug)->update(['license_required' => $required, 'license_hint' => $hint]);
        }

        // Carry over what existing providers already uploaded.
        foreach (DB::table('provider_profiles')->get() as $p) {
            $status = $p->verification_status === 'approved' ? 'approved' : 'pending';
            foreach (['nrc_document' => 'nrc_front', 'business_document' => 'business_cert'] as $column => $type) {
                if ($p->{$column}) {
                    DB::table('provider_documents')->insert([
                        'provider_profile_id' => $p->id, 'type' => $type, 'path' => $p->{$column}, 'original_name' => 'Earlier upload',
                        'status' => $status, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            if ($p->nrc_number) {
                DB::table('users')->where('id', $p->user_id)->update(['nrc_number' => normalize_nrc($p->nrc_number) ?? $p->nrc_number]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['license_required', 'license_hint']));
        Schema::table('provider_profiles', fn (Blueprint $t) => $t->dropColumn(['payout_method', 'payout_phone', 'payout_account_name', 'bank_name', 'bank_account_number', 'bank_branch']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['nrc_number', 'latitude', 'longitude']));
    }
};
