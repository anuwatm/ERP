<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_runtime_fence', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->string('generation', 100);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_runtime_fence');
    }
};
