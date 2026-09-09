<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->unique()->constrained('organizations')->restrictOnDelete();
            $table->string('provider')->default('settlement_hmac');
            $table->boolean('enabled')->default(false);
            $table->string('qr_type')->default('biller');
            $table->text('recipient_id');
            $table->text('webhook_secret');
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('payment_gateway_config_id')->constrained('payment_gateway_configs')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->string('reference', 20)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('THB');
            $table->text('qr_payload');
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'invoice_id', 'status']);
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_gateway_config_id')->constrained('payment_gateway_configs')->restrictOnDelete();
            $table->string('event_id', 100);
            $table->string('payload_hash', 64);
            $table->string('status');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['payment_gateway_config_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('payment_gateway_configs');
    }
};
