<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移 - 建立訂單表
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // 主鍵
            $table->id();
            // 會員 ID
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 訂單編號（格式：ORD-YYYYMMDD-XXXXX）
            $table->string('order_number', 20)->unique();
            // 訂單狀態：pending/processing/shipped/completed/cancelled
            $table->string('status', 20)->default('pending');
            // 商品小計（不含運費）
            $table->decimal('subtotal', 10, 2);
            // 運費
            $table->decimal('shipping_fee', 10, 2)->default(0);
            // 訂單總金額（含運費）
            $table->decimal('total_amount', 10, 2);
            // 收件人姓名
            $table->string('shipping_name', 50);
            // 收件人電話
            $table->string('shipping_phone', 20);
            // 收件人電子郵件
            $table->string('shipping_email', 100)->nullable();
            // 收件郵遞區號
            $table->string('shipping_postal_code', 10)->nullable();
            // 收件城市
            $table->string('shipping_city', 50)->nullable();
            // 收件地址
            $table->string('shipping_address', 200);
            // 運送方式 (standard/express/convenience)
            $table->string('shipping_method', 20)->default('standard');
            // 訂單備註
            $table->text('notes')->nullable();
            // 付款時間
            $table->timestamp('paid_at')->nullable();
            // 出貨時間
            $table->timestamp('shipped_at')->nullable();
            // 完成時間
            $table->timestamp('completed_at')->nullable();
            // 取消時間
            $table->timestamp('cancelled_at')->nullable();
            // 時間戳記
            $table->timestamps();

            // 索引
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
