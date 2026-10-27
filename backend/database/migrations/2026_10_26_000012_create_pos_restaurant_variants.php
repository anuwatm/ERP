<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_restaurant_variants', function (Blueprint $t) {
            $t->foreignUuid('product_id')->primary()->constrained('products');
            $t->foreignUuid('org_id')->constrained('organizations');
            $t->foreignUuid('parent_product_id')->constrained('products');
            $t->string('label', 100);
            $t->string('size', 60);
            $t->json('modifiers');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_restaurant_variants');
    }
};
