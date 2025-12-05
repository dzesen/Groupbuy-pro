<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 团购活动表 (Campaigns) - 如 "Chiikawa 8月发售"
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // 活动标题
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. 商品表 (Products)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('price', 10, 2); // 单价
            $table->integer('limit_per_person')->default(1); // 单人限购
            $table->integer('stock_total')->default(9999); // 总库存
            $table->timestamps();
        });

        // 3. 车队表 (Groups) - 即 "一车", "二车"
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('leader_id')->constrained('users'); // 车头(团长/组长)
            $table->string('name'); // 如 "上海徐汇分车"
            
            // 成团条件
            $table->enum('target_type', ['amount', 'quantity']); // 按金额还是按数量
            $table->integer('target_value'); // 目标值 (如 500元 或 10个)
            
            // 状态
            $table->enum('status', ['building', 'locked', 'completed', 'failed'])->default('building');
            
            $table->timestamps();
        });

        // 4. 车队成员表 (Group Members)
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('role', ['leader', 'member'])->default('member');
            $table->decimal('total_amount', 10, 2)->default(0); // 该成员总消费
            $table->timestamps();

            $table->unique(['group_id', 'user_id']); // 防止重复加车
        });

        // 5. 订单明细表 (Order Items) - 记录每个人买了什么
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('price_snapshot', 10, 2); // 下单时的价格快照
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('products');
        Schema::dropIfExists('campaigns');
    }
};