<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('price')->nullable();
            $table->json('items')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author');
            $table->unsignedTinyInteger('rating')->default(5); // 1-5 stars
            $table->string('stars')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
        Schema::dropIfExists('vendor_images');
        Schema::dropIfExists('vendor_packages');
    }
};
