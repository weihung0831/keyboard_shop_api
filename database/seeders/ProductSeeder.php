<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * 各分類的商品數量設定
     */
    protected array $categoryProductCounts = [
        'mechanical-keyboards' => 25,  // 機械鍵盤
        'keycaps' => 20,               // 鍵帽
        'switches' => 20,              // 軸體
        'keyboard-accessories' => 20,  // 配件
        'custom-kits' => 15,           // 客製化套件
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 取得所有分類
        $categories = ProductCategory::all();

        if ($categories->isEmpty()) {
            $this->command->warn('請先執行 ProductCategorySeeder');
            return;
        }

        $totalProducts = 0;
        $totalImages = 0;

        // 為每個分類建立商品
        foreach ($categories as $category) {
            $count = $this->categoryProductCounts[$category->slug] ?? 15;

            // 根據分類產生對應的商品
            $products = Product::factory()
                ->count($count)
                ->forCategory($category->slug)
                ->create([
                    'category_id' => $category->id,
                    'is_active' => true,
                ]);

            // 為每個商品建立圖片
            foreach ($products as $product) {
                // 建立 1 張主圖
                ProductImage::factory()
                    ->forCategory($category->slug)
                    ->primary()
                    ->create([
                        'product_id' => $product->id,
                    ]);

                // 建立 2-4 張附圖
                $additionalImagesCount = rand(2, 4);
                for ($i = 1; $i <= $additionalImagesCount; $i++) {
                    ProductImage::factory()
                        ->forCategory($category->slug)
                        ->sortOrder($i)
                        ->create([
                            'product_id' => $product->id,
                        ]);
                }

                $totalImages += 1 + $additionalImagesCount;
            }

            $totalProducts += $count;
            $this->command->info("已為分類「{$category->name}」建立 {$count} 筆商品");
        }

        $this->command->info("總共建立 {$totalProducts} 筆商品，{$totalImages} 張圖片");
    }
}
