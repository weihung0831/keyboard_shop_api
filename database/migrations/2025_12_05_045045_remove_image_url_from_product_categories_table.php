<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * 移除 image_url 欄位
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }

    /**
     * 還原 image_url 欄位
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('description')->comment('分類圖片');
        });
    }
};
