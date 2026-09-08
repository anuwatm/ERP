<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('name', 100);
            $table->text('config');
            $table->boolean('is_enabled')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'channel']);
        });

        Schema::create('user_channel_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->boolean('enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('urgent_only')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'channel']);
        });

        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('channel', 20);
            $table->string('priority', 20)->default('normal');
            $table->json('payload');
            $table->string('idempotency_key', 191);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'idempotency_key']);
            $table->index(['status', 'available_at']);
            $table->index(['org_id', 'event_type']);
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_outbox_id')->constrained('notification_outbox')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->unsignedTinyInteger('attempt_no');
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('dispatched_at');
            $table->timestamps();
            $table->unique(['notification_outbox_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('user_channel_preferences');
        Schema::dropIfExists('notification_channels');
    }
};
