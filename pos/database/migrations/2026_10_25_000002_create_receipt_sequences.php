<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_receipt_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('terminal_id')->constrained('pos_terminals')->restrictOnDelete();
            $table->date('business_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['terminal_id', 'business_date'], 'pos_receipt_sequence_scope');
        });
        foreach (DB::table('pos_orders')->select('terminal_id', 'receipt_no')->cursor() as $order) {
            if (preg_match('/-(\d{8})-(\d+)$/D', $order->receipt_no, $matches)) {
                $date = DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);
                if (! $date) {
                    continue;
                }
                $scope = ['terminal_id' => $order->terminal_id, 'business_date' => $date->format('Y-m-d')];
                $last = DB::table('pos_receipt_sequences')->where($scope)->value('last_number') ?? 0;
                DB::table('pos_receipt_sequences')->updateOrInsert($scope, ['last_number' => max($last, (int) $matches[2])]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipt_sequences');
    }
};
