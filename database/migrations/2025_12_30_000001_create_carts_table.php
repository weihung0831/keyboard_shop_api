<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移 - 建立購物車表
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            // 主鍵
            $table->id();
            // 會員 ID（nullable，允許訪客購物車）
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            // 訪客 Session ID
            $table->string('session_id', 100)->nullable();
            // 時間戳記
            $table->timestamps();

            // 索引
            $table->index('session_id');
            $table->index(['user_id', 'session_id']);
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
