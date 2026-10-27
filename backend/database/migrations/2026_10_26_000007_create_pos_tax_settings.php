<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tax_settings', function (Blueprint $table) {
            $table->foreignUuid('org_id')->primary()->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('signing_owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_tax_settings');
    }
};
