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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade')
                  ->comment('商品 ID');
            $table->string('image_url')->comment('圖片 URL');
            $table->boolean('is_primary')->default(false)->comment('是否為主圖');
            $table->integer('sort_order')->default(0)->comment('排序順序');
            $table->timestamps();

            $table->index('product_id');
            $table->index('is_primary');
            $table->index(['product_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
