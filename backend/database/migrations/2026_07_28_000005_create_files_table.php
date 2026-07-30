<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('storage_key', 500);
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('entity_type', 30)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['org_id', 'storage_key']);
            $table->index(['org_id', 'entity_type', 'entity_id']);
            $table->index(['org_id', 'category']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('attachment_file_id')->references('id')->on('files')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('receipt_file_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['receipt_file_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['attachment_file_id']);
        });

        Schema::dropIfExists('files');
    }
};
