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
        Schema::create('payments', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('capture_method', 16);
            $table->unsignedBigInteger('amount_captured')->default(0);
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->string('card_brand', 16);
            $table->char('card_last4', 4);
            $table->unsignedTinyInteger('card_exp_month');
            $table->unsignedSmallInteger('card_exp_year');
            $table->char('card_fingerprint', 64);
            $table->string('failure_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->string('payment_id', 40);
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->string('status', 32);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
