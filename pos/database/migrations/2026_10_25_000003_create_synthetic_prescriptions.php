<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('pos_branches');
            $table->foreignUuid('uploaded_by')->constrained('pos_staff');
            $table->foreignUuid('order_id')->nullable()->constrained('pos_orders');
            $table->string('private_path');
            $table->string('mime', 80);
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->string('status', 20)->default('quarantined');
            $table->timestamp('retained_until');
            $table->boolean('legal_hold')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_prescriptions');
    }
};
