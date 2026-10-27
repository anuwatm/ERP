<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_menu_tokens', fn (Blueprint $t) => $t->foreignUuid('table_session_id')->nullable()->constrained('pos_table_sessions'));
        Schema::table('pos_customer_requests', fn (Blueprint $t) => $t->foreignUuid('restaurant_order_id')->nullable()->constrained('pos_kitchen_orders'));
    }

    public function down(): void
    {
        Schema::table('pos_customer_requests', function (Blueprint $t) {
            $t->dropForeign(['restaurant_order_id']);
            $t->dropColumn('restaurant_order_id');
        });
        Schema::table('pos_menu_tokens', function (Blueprint $t) {
            $t->dropForeign(['table_session_id']);
            $t->dropColumn('table_session_id');
        });
    }
};
