<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            $table->text('provider_secret')->nullable();
            $table->boolean('livemode')->default(false);
        });
        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->string('provider_charge_id')->nullable()->unique();
            $table->timestamp('provider_confirmed_at')->nullable();
            $table->timestamp('provider_paid_at')->nullable();
            $table->longText('provider_qr_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gateway_transactions', fn (Blueprint $table) => $table->dropColumn(['provider_charge_id', 'provider_confirmed_at', 'provider_paid_at', 'provider_qr_image']));
        Schema::table('payment_gateway_configs', fn (Blueprint $table) => $table->dropColumn(['provider_secret', 'livemode']));
    }
};
