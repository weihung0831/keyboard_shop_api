<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 清除 products.content 欄位中的 HTML 標籤
     */
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNotNull('content')
            ->get(['id', 'content']);

        foreach ($products as $product) {
            $clean = strip_tags($product->content);
            $clean = preg_replace('/\n{3,}/', "\n\n", trim($clean));

            DB::table('products')
                ->where('id', $product->id)
                ->update(['content' => $clean]);
        }
    }

    /**
     * 無法還原（HTML 標籤已移除）
     */
    public function down(): void
    {
        // irreversible
    }
};
