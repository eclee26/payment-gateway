<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('driver', 64);
            $table->text('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 16);
            $table->unsignedInteger('fee_bps')->default(0);
            $table->unsignedBigInteger('fee_fixed')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'payment_method']);
        });

        Schema::create('merchant_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 16);
            $table->foreignId('provider_payment_method_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['merchant_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_methods');
        Schema::dropIfExists('provider_payment_methods');
        Schema::dropIfExists('providers');
    }
};
