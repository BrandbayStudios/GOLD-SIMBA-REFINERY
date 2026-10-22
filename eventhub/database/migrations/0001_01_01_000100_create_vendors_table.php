<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('category');
            $table->string('location');
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('experience')->nullable();
            $table->string('events_count')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('cover')->nullable();
            $table->string('logo')->nullable();
            $table->text('about')->nullable();
            $table->json('services')->nullable();
            $table->json('event_types')->nullable();
            $table->string('facebook_url')->nullable();
            $table->unsignedInteger('price_from')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('featured')->default(false);
            $table->string('status')->default('approved'); // pending | approved | rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
