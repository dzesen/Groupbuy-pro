<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // ⭐ 关键：这里的字段是“团长自定义”的，只对当前车队生效
            $table->decimal('sell_price', 10, 2)->nullable(); // 团长定的卖价 (可覆盖原价)
            $table->integer('limit_per_person')->default(1);  // 团长定的限购
            $table->boolean('is_active')->default(true);      // 团长可临时下架某商品

            $table->timestamps();

            // 防止同一个商品在一个车队里重复添加
            $table->unique(['group_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_product');
    }
};
