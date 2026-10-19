<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('integration_api_key_id')->constrained('integration_api_keys')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->text('signing_secret');
            $table->json('events');
            $table->string('status', 20)->default('paused');
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['org_id', 'status']);
        });
        Schema::create('integration_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('resource_type', 40);
            $table->uuid('resource_id');
            $table->json('payload');
            $table->timestamps();
            $table->index(['org_id', 'created_at']);
        });
        Schema::create('integration_webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('integration_webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->foreignUuid('integration_webhook_event_id')->constrained('integration_webhook_events')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('locked_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error', 60)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_webhook_id', 'integration_webhook_event_id'], 'integration_delivery_event_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_deliveries');
        Schema::dropIfExists('integration_webhook_events');
        Schema::dropIfExists('integration_webhooks');
    }
};
