<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductSpecification;
use Illuminate\Database\Seeder;

class ProductSpecificationSeeder extends Seeder
{
    /**
     * 機械鍵盤可能的規格值
     */
    protected array $switches = [
        'Cherry MX 紅軸', 'Cherry MX 青軸', 'Cherry MX 茶軸',
        'Gateron Pro 紅軸', 'Gateron Pro 黃軸', '靜電容',
    ];

    protected array $layouts = ['60%', '65%', '75%', '80% TKL', '100% 全尺寸'];

    protected array $connections = ['有線 USB-C', '藍牙 5.0', '2.4G 無線', '藍牙/有線 雙模', '三模連接'];

    /**
     * 鍵帽規格值
     */
    protected array $keycapMaterials = ['PBT 雙色注塑', 'ABS 雙色注塑', 'PBT 熱昇華', 'POM'];

    protected array $keycapProfiles = ['Cherry', 'OEM', 'SA', 'DSA', 'XDA', 'KAT', 'MT3'];

    /**
     * 軸體規格值
     */
    protected array $switchTypes = ['線性', '段落', '段落聲響'];

    protected array $switchWeights = ['35g', '40g', '45g', '50g', '55g', '60g', '67g'];

    /**
     * 客製化套件規格值
     */
    protected array $kitMaterials = ['鋁合金', 'PC 塑膠', '黃銅', '不鏽鋼', '陽極氧化鋁'];

    protected array $kitPlates = ['PC 定位板', '鋁定位板', 'FR4 定位板', '黃銅定位板', 'POM 定位板'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清除舊規格
        ProductSpecification::truncate();

        $products = Product::with('category')->get();
        $count = 0;

        foreach ($products as $product) {
            $categorySlug = $product->category?->slug;
            $specs = match ($categorySlug) {
                'mechanical-keyboards' => $this->keyboardSpecs($product),
                'keycaps' => $this->keycapSpecs($product),
                'switches' => $this->switchSpecs($product),
                'keyboard-accessories' => $this->accessorySpecs($product),
                'custom-kits' => $this->customKitSpecs($product),
                default => [],
            };

            foreach ($specs as $i => $spec) {
                ProductSpecification::create([
                    'product_id' => $product->id,
                    'spec_name' => $spec['name'],
                    'spec_value' => $spec['value'],
                    'sort_order' => $i,
                ]);
                $count++;
            }
        }

        $this->command->info("已為 {$products->count()} 個商品建立 {$count} 筆規格");
    }

    /**
     * 從描述中擷取值，找不到就隨機給
     */
    protected function extractOrRandom(string $text, string $pattern, array $fallback): string
    {
        foreach ($fallback as $value) {
            if (str_contains($text, $value)) {
                return $value;
            }
        }

        return fake()->randomElement($fallback);
    }

    /**
     * 機械鍵盤規格
     */
    protected function keyboardSpecs(Product $product): array
    {
        $desc = $product->description . ' ' . $product->content;

        return [
            ['name' => '軸體', 'value' => $this->extractOrRandom($desc, '', $this->switches)],
            ['name' => '鍵盤配列', 'value' => $this->extractOrRandom($desc, '', $this->layouts)],
            ['name' => '連接方式', 'value' => $this->extractOrRandom($desc, '', $this->connections)],
            ['name' => '熱插拔', 'value' => fake()->randomElement(['支援', '不支援'])],
            ['name' => 'RGB 背光', 'value' => fake()->randomElement(['支援', '單色背光', '無背光'])],
        ];
    }

    /**
     * 鍵帽規格
     */
    protected function keycapSpecs(Product $product): array
    {
        $desc = $product->description . ' ' . $product->content;

        return [
            ['name' => '材質', 'value' => $this->extractOrRandom($desc, '', $this->keycapMaterials)],
            ['name' => '高度', 'value' => $this->extractOrRandom($desc, '', $this->keycapProfiles)],
            ['name' => '鍵數', 'value' => fake()->randomElement(['104鍵', '108鍵', '127鍵', '140鍵'])],
            ['name' => '相容軸體', 'value' => 'Cherry MX 相容軸'],
        ];
    }

    /**
     * 軸體規格
     */
    protected function switchSpecs(Product $product): array
    {
        $desc = $product->description . ' ' . $product->content;

        return [
            ['name' => '軸體類型', 'value' => $this->extractOrRandom($desc, '', $this->switchTypes)],
            ['name' => '觸發壓力', 'value' => fake()->randomElement($this->switchWeights)],
            ['name' => '觸發行程', 'value' => fake()->randomElement(['1.5mm', '2.0mm', '2.2mm'])],
            ['name' => '總行程', 'value' => fake()->randomElement(['3.4mm', '3.6mm', '4.0mm'])],
        ];
    }

    /**
     * 配件規格
     */
    protected function accessorySpecs(Product $product): array
    {
        $name = $product->name;

        if (str_contains($name, '線材')) {
            return [
                ['name' => '長度', 'value' => fake()->randomElement(['1.2m', '1.5m', '1.8m', '2.0m'])],
                ['name' => '接頭', 'value' => 'USB-C to USB-A'],
                ['name' => '線材材質', 'value' => fake()->randomElement(['編織尼龍', 'PVC', '矽膠'])],
            ];
        }

        if (str_contains($name, '潤滑油')) {
            return [
                ['name' => '容量', 'value' => fake()->randomElement(['3ml', '5ml', '10ml'])],
                ['name' => '適用範圍', 'value' => '線性軸/段落軸/穩定器'],
            ];
        }

        if (str_contains($name, '腕托')) {
            return [
                ['name' => '尺寸', 'value' => fake()->randomElement(['60%', '65%', 'TKL', '全尺寸'])],
                ['name' => '材質', 'value' => fake()->randomElement(['實木', '皮革', '記憶棉', '矽膠'])],
            ];
        }

        if (str_contains($name, '穩定器')) {
            return [
                ['name' => '規格', 'value' => fake()->randomElement(['PCB 卡扣式', '鋼板嵌入式'])],
                ['name' => '材質', 'value' => fake()->randomElement(['POM', 'Nylon', 'PC'])],
            ];
        }

        return [
            ['name' => '類型', 'value' => '鍵盤配件'],
            ['name' => '相容性', 'value' => '通用'],
        ];
    }

    /**
     * 客製化套件規格
     */
    protected function customKitSpecs(Product $product): array
    {
        $desc = $product->description . ' ' . $product->content;

        return [
            ['name' => '鍵盤配列', 'value' => $this->extractOrRandom($desc, '', $this->layouts)],
            ['name' => '外殼材質', 'value' => $this->extractOrRandom($desc, '', $this->kitMaterials)],
            ['name' => '定位板', 'value' => $this->extractOrRandom($desc, '', $this->kitPlates)],
            ['name' => '連接方式', 'value' => fake()->randomElement(['有線 USB-C', '三模連接'])],
            ['name' => '熱插拔', 'value' => '支援'],
        ];
    }
}
