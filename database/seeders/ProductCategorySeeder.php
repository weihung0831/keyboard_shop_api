<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => '機械鍵盤',
                'slug' => 'mechanical-keyboards',
                'description' => '各式機械鍵盤，包含有線、無線、客製化等多種選擇',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '鍵帽',
                'slug' => 'keycaps',
                'description' => '客製化鍵帽，多種材質、配色與刻印可選',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => '軸體',
                'slug' => 'switches',
                'description' => '各大品牌機械軸體，紅軸、青軸、茶軸等多種手感',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => '鍵盤配件',
                'slug' => 'keyboard-accessories',
                'description' => '鍵盤線材、潤滑油、拔鍵器等配件',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => '客製化套件',
                'slug' => 'custom-kits',
                'description' => '客製化鍵盤套件，DIY 愛好者首選',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }

        $this->command->info('已建立 ' . count($categories) . ' 個商品分類');
    }
}
