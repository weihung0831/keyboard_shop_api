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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分類名稱');
            $table->string('slug')->unique()->comment('URL 代稱');
            $table->text('description')->nullable()->comment('分類描述');
            $table->string('image_url')->nullable()->comment('分類圖片 URL');
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            $table->integer('sort_order')->default(0)->comment('排序順序');
            $table->timestamps();

            $table->index('name');
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
