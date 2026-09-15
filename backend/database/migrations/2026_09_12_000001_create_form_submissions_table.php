<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_key');
            $table->string('fingerprint', 64);
            $table->json('result');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
