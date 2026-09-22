<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['org_id', 'status']);
            $table->index(['org_id', 'name']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignUuid('position_id')->nullable()->after('position_title')->constrained('positions')->nullOnDelete();
            $table->index(['org_id', 'position_id']);
        });

        // Backfill existing positions from employees.position_title
        if (Schema::hasTable('employees')) {
            $distinctTitles = DB::table('employees')
                ->whereNotNull('position_title')
                ->where('position_title', '!=', '')
                ->select('org_id', 'department_id', 'position_title')
                ->distinct()
                ->get();

            foreach ($distinctTitles as $row) {
                $name = trim($row->position_title);
                if (empty($name)) {
                    continue;
                }

                $existing = DB::table('positions')
                    ->where('org_id', $row->org_id)
                    ->where('name', $name)
                    ->first();

                $positionId = $existing?->id;
                if (! $positionId) {
                    $positionId = (string) Str::orderedUuid();
                    DB::table('positions')->insert([
                        'id' => $positionId,
                        'org_id' => $row->org_id,
                        'department_id' => $row->department_id,
                        'name' => $name,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('employees')
                    ->where('org_id', $row->org_id)
                    ->where('position_title', $row->position_title)
                    ->whereNull('position_id')
                    ->update(['position_id' => $positionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropIndex(['org_id', 'position_id']);
            $table->dropColumn('position_id');
        });

        Schema::dropIfExists('positions');
    }
};
