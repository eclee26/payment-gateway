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
        Schema::table('payments', function (Blueprint $table) {
            // Existing rows were all card payments.
            $table->string('payment_method', 16)->default('CARD')->after('merchant_id');
            $table->foreignId('provider_id')->nullable()->after('payment_method')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('fee_amount')->default(0)->after('amount_refunded');
            $table->string('provider_reference', 128)->nullable()->after('provider_id');
            $table->json('next_action')->nullable()->after('failure_code');
            $table->timestamp('expires_at')->nullable()->after('next_action');

            // Non-card payment methods have no card.
            $table->string('card_brand', 16)->nullable()->change();
            $table->char('card_last4', 4)->nullable()->change();
            $table->unsignedTinyInteger('card_exp_month')->nullable()->change();
            $table->unsignedSmallInteger('card_exp_year')->nullable()->change();
            $table->char('card_fingerprint', 64)->nullable()->change();

            $table->index(['provider_id', 'provider_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'provider_reference']);
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn(['payment_method', 'fee_amount', 'provider_reference', 'next_action', 'expires_at']);
        });
    }
};
