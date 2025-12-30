<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移 - 建立購物車項目表
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            // 主鍵
            $table->id();
            // 購物車 ID
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            // 商品 ID
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            // 購買數量
            $table->unsignedInteger('quantity')->default(1);
            // 加入時的商品價格（快照）
            $table->decimal('price', 10, 2);
            // 時間戳記
            $table->timestamps();

            // 同一購物車不能有重複商品
            $table->unique(['cart_id', 'product_id']);
            // 索引
            $table->index('product_id');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
