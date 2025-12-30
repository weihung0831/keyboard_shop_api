<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移 - 建立訂單明細表
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            // 主鍵
            $table->id();
            // 訂單 ID
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            // 商品 ID（保留關聯但允許商品被刪除）
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            // 商品名稱快照
            $table->string('product_name', 100);
            // 商品編號快照
            $table->string('product_sku', 50);
            // 購買數量
            $table->unsignedInteger('quantity');
            // 商品單價快照
            $table->decimal('price', 10, 2);
            // 小計金額
            $table->decimal('subtotal', 10, 2);
            // 時間戳記
            $table->timestamps();

            // 索引
            $table->index('product_id');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
