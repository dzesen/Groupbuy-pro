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
        Schema::table('products', function (Blueprint $table) {
            // 原币种价格 (例如 1100)
            $table->decimal('original_price', 10, 2)->nullable()->after('name');
            // 货币单位 (例如 JPY)
            $table->string('currency')->default('JPY')->after('original_price');
            // 汇率 (例如 0.055)
            $table->decimal('exchange_rate', 10, 5)->default(0.05)->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
