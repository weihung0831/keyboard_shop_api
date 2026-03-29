<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 將 products.specifications JSON 資料遷移到 product_specifications 表
     */
    public function up(): void
    {
        $products = DB::table('products')
            ->whereNotNull('specifications')
            ->get(['id', 'specifications']);

        $sort = 0;
        foreach ($products as $product) {
            $specs = json_decode($product->specifications, true);
            if (! is_array($specs)) {
                continue;
            }

            $sort = 0;
            foreach ($specs as $name => $value) {
                DB::table('product_specifications')->insert([
                    'product_id' => $product->id,
                    'spec_name' => $name,
                    'spec_value' => is_string($value) ? $value : json_encode($value),
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse: 從 product_specifications 表回寫 JSON 到 products.specifications
     */
    public function down(): void
    {
        $specs = DB::table('product_specifications')
            ->orderBy('product_id')
            ->orderBy('sort_order')
            ->get();

        $grouped = $specs->groupBy('product_id');

        foreach ($grouped as $productId => $productSpecs) {
            $json = [];
            foreach ($productSpecs as $spec) {
                $json[$spec->spec_name] = $spec->spec_value;
            }

            DB::table('products')
                ->where('id', $productId)
                ->update(['specifications' => json_encode($json)]);
        }
    }
};
