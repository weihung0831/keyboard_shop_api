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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('product_categories')
                  ->onDelete('cascade')
                  ->comment('分類 ID');
            $table->string('name')->comment('商品名稱');
            $table->string('slug')->unique()->comment('URL 代稱');
            $table->text('description')->nullable()->comment('簡短描述');
            $table->longText('content')->nullable()->comment('完整介紹 (HTML)');
            $table->string('sku', 100)->unique()->comment('商品編號');
            $table->decimal('price', 10, 2)->comment('售價');
            $table->decimal('original_price', 10, 2)->nullable()->comment('原價');
            $table->integer('stock')->default(0)->comment('庫存數量');
            $table->json('specifications')->nullable()->comment('商品規格 (JSON)');
            $table->boolean('is_active')->default(true)->comment('是否上架');
            $table->integer('sort_order')->default(0)->comment('排序順序');
            $table->timestamps();

            $table->index('name');
            $table->index('category_id');
            $table->index('price');
            $table->index('stock');
            $table->index('is_active');
            $table->index('created_at');
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
