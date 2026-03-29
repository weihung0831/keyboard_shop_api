<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * 各分類的商品資料
     */
    protected static array $categoryProducts = [
        // 機械鍵盤
        'mechanical-keyboards' => [
            'brands' => ['Keychron', 'HHKB', 'Filco', 'Leopold', 'Varmilo', 'Ducky', 'REALFORCE', 'NuPhy', 'IQUNIX', 'Akko'],
            'models' => [
                'Keychron' => ['K2 Pro', 'K3 Pro', 'K6 Pro', 'K8 Pro', 'Q1', 'Q2', 'Q3', 'V1', 'V2', 'V3'],
                'HHKB' => ['Professional HYBRID', 'Professional HYBRID Type-S', 'Studio', 'Classic'],
                'Filco' => ['Majestouch 2', 'Majestouch 3', 'Minila-R', 'Convertible 3'],
                'Leopold' => ['FC660M', 'FC750R', 'FC900R', 'FC980M'],
                'Varmilo' => ['VA87M', 'VA108M', 'VA68M', 'Minilo'],
                'Ducky' => ['One 2 Mini', 'One 2 SF', 'One 3', 'Shine 7'],
                'REALFORCE' => ['R3', 'R2', 'R3S', 'GX1'],
                'NuPhy' => ['Air75 V2', 'Air96 V2', 'Halo75 V2', 'Field75'],
                'IQUNIX' => ['F96', 'L80', 'A80', 'OG80'],
                'Akko' => ['5075B Plus', '3098B', 'MOD007', 'ACR Pro'],
            ],
            'price_range' => [2500, 12000],
            'switches' => ['Cherry MX 紅軸', 'Cherry MX 青軸', 'Cherry MX 茶軸', 'Gateron Pro 紅軸', 'Gateron Pro 黃軸', '靜電容'],
            'layouts' => ['60%', '65%', '75%', '80% TKL', '100% 全尺寸'],
            'connections' => ['有線 USB-C', '藍牙 5.0', '2.4G 無線', '藍牙/有線 雙模', '三模連接'],
        ],
        // 鍵帽
        'keycaps' => [
            'brands' => ['GMK', 'JTK', 'PBTfans', 'ePBT', 'Akko', 'Drop', 'Tai-Hao', 'HK Gaming', 'XDA', 'DSA'],
            'themes' => ['Olivia', 'Botanical', '8008', 'Dracula', 'Nord', 'Nautilus', 'Carbon', 'Laser', 'Dots', 'WoB', 'BoW', 'Minimal', 'Apollo', 'Striker'],
            'materials' => ['PBT 雙色注塑', 'ABS 雙色注塑', 'PBT 熱昇華', 'POM'],
            'profiles' => ['Cherry', 'OEM', 'SA', 'DSA', 'XDA', 'KAT', 'MT3'],
            'price_range' => [800, 5000],
        ],
        // 軸體
        'switches' => [
            'brands' => ['Cherry MX', 'Gateron', 'Kailh', 'TTC', 'Durock', 'JWK', 'Outemu', 'Akko', 'Tecsee', 'SP-Star'],
            'types' => [
                'Cherry MX' => ['紅軸', '青軸', '茶軸', '黑軸', '銀軸', '靜音紅軸'],
                'Gateron' => ['Pro 紅軸', 'Pro 黃軸', 'Oil King', 'Milky Yellow', 'CJ'],
                'Kailh' => ['Box White', 'Box Red', 'Box Brown', 'Speed Silver', 'Cream'],
                'TTC' => ['金粉軸', 'ACE', '烈焰紅', '快銀'],
                'Durock' => ['POM', 'L7', 'T1', 'Sunflower'],
                'JWK' => ['Black', 'Red', 'Alpaca', 'Lavender'],
                'Akko' => ['CS 銀軸', 'CS 抹茶綠', 'CS 果凍粉', 'V3 Cream Yellow'],
            ],
            'price_range' => [150, 800],
            'quantity' => ['10顆裝', '35顆裝', '70顆裝', '90顆裝', '110顆裝'],
        ],
        // 鍵盤配件
        'keyboard-accessories' => [
            'types' => [
                ['name' => '客製化線材', 'brands' => ['CableMod', 'Mechcables', 'Space Cables', 'Custom Cable'], 'price_range' => [500, 2000]],
                ['name' => '潤滑油', 'brands' => ['Krytox GPL 205g0', 'Tribosys 3203', 'Tribosys 3204', 'Christo-Lube'], 'price_range' => [200, 600]],
                ['name' => '拔鍵器', 'brands' => ['通用款', '金屬款', 'IC 拔軸器', '二合一拔鍵拔軸器'], 'price_range' => [50, 300]],
                ['name' => '鍵盤墊', 'brands' => ['矽膠消音墊', 'PE 泡棉', 'IXPE 泡棉', '底殼填充棉'], 'price_range' => [100, 500]],
                ['name' => '穩定器', 'brands' => ['Durock V2', 'Cherry 原廠', 'Gateron INK', 'TX Stabilizers'], 'price_range' => [200, 800]],
                ['name' => '腕托', 'brands' => ['木製腕托', '皮革腕托', '記憶棉腕托', '矽膠腕托'], 'price_range' => [300, 1500]],
            ],
        ],
        // 客製化套件
        'custom-kits' => [
            'brands' => ['KBDfans', 'Cannonkeys', 'Mode', 'Keychron', 'GMMK', 'Zoom65', 'QK65', 'Freebird', 'Monokei'],
            'kits' => [
                'KBDfans' => ['D60', 'D65', 'D84', 'Odin', 'Tiger 80'],
                'Cannonkeys' => ['Satisfaction 75', 'Bakeneko', 'Brutal V2', 'Onyx'],
                'Mode' => ['Sonnet', 'Envoy', 'Eighty'],
                'Keychron' => ['Q1 Pro Barebone', 'Q2 Pro Barebone', 'Q3 Barebone', 'Q5 Barebone'],
                'GMMK' => ['GMMK Pro', 'GMMK 2', 'GMMK Numpad'],
                'Zoom65' => ['Zoom65 Essential', 'Zoom65 V2', 'Zoom65 V3'],
                'QK65' => ['QK65 R2', 'QK65 R3'],
            ],
            'price_range' => [3000, 15000],
            'materials' => ['鋁合金', 'PC 塑膠', '黃銅', '不鏽鋼', '陽極氧化鋁'],
            'plates' => ['PC 定位板', '鋁定位板', 'FR4 定位板', '黃銅定位板', 'POM 定位板'],
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 預設使用機械鍵盤分類的資料
        return $this->generateMechanicalKeyboard();
    }

    /**
     * 根據分類產生商品資料
     */
    public function forCategory(string $slug): static
    {
        return $this->state(function (array $attributes) use ($slug) {
            return match ($slug) {
                'mechanical-keyboards' => $this->generateMechanicalKeyboard(),
                'keycaps' => $this->generateKeycaps(),
                'switches' => $this->generateSwitches(),
                'keyboard-accessories' => $this->generateAccessories(),
                'custom-kits' => $this->generateCustomKits(),
                default => $this->generateMechanicalKeyboard(),
            };
        });
    }

    /**
     * 產生機械鍵盤資料
     */
    protected function generateMechanicalKeyboard(): array
    {
        $data = self::$categoryProducts['mechanical-keyboards'];
        $brand = fake()->randomElement($data['brands']);
        $models = $data['models'][$brand] ?? ['Standard'];
        $model = fake()->randomElement($models);
        $name = "{$brand} {$model} 機械鍵盤";
        $switch = fake()->randomElement($data['switches']);
        $layout = fake()->randomElement($data['layouts']);
        $connection = fake()->randomElement($data['connections']);

        $price = fake()->numberBetween($data['price_range'][0], $data['price_range'][1]);
        $hasDiscount = fake()->boolean(30);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => "高品質 {$brand} {$model} 機械鍵盤，採用 {$switch}，{$layout} 配置，{$connection}。適合打字、程式設計與遊戲使用。",
            'content' => $this->generateKeyboardContent($brand, $model, $switch, $layout, $connection),
            'sku' => strtoupper($brand[0].$brand[1]).'-'.fake()->unique()->numerify('####-###'),
            'price' => $price,
            'original_price' => $hasDiscount ? round($price * fake()->randomFloat(2, 1.15, 1.35)) : null,
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 產生鍵帽資料
     */
    protected function generateKeycaps(): array
    {
        $data = self::$categoryProducts['keycaps'];
        $brand = fake()->randomElement($data['brands']);
        $theme = fake()->randomElement($data['themes']);
        $material = fake()->randomElement($data['materials']);
        $profile = fake()->randomElement($data['profiles']);
        $name = "{$brand} {$theme} 鍵帽組";

        $price = fake()->numberBetween($data['price_range'][0], $data['price_range'][1]);
        $hasDiscount = fake()->boolean(25);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => "{$brand} {$theme} 主題鍵帽組，{$material}材質，{$profile} 高度。精美配色，手感舒適。",
            'content' => $this->generateKeycapsContent($brand, $theme, $material, $profile),
            'sku' => 'KC-'.fake()->unique()->numerify('####-###'),
            'price' => $price,
            'original_price' => $hasDiscount ? round($price * 1.2) : null,
            'stock' => fake()->numberBetween(0, 30),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 產生軸體資料
     */
    protected function generateSwitches(): array
    {
        $data = self::$categoryProducts['switches'];
        $brand = fake()->randomElement($data['brands']);
        $types = $data['types'][$brand] ?? ['標準款'];
        $type = fake()->randomElement($types);
        $quantity = fake()->randomElement($data['quantity']);
        $name = "{$brand} {$type} ({$quantity})";

        $basePrice = fake()->numberBetween($data['price_range'][0], $data['price_range'][1]);
        $multiplier = (int) filter_var($quantity, FILTER_SANITIZE_NUMBER_INT);
        $price = round($basePrice * ($multiplier / 10));
        $hasDiscount = fake()->boolean(20);

        $switchType = fake()->randomElement(['線性', '段落', '段落聲響']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => "{$brand} {$type}，{$switchType}手感，{$quantity}。適合 DIY 客製化鍵盤使用。",
            'content' => $this->generateSwitchesContent($brand, $type, $switchType),
            'sku' => 'SW-'.fake()->unique()->numerify('####-###'),
            'price' => $price,
            'original_price' => $hasDiscount ? round($price * 1.15) : null,
            'stock' => fake()->numberBetween(0, 200),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 產生配件資料
     */
    protected function generateAccessories(): array
    {
        $data = self::$categoryProducts['keyboard-accessories'];
        $typeData = fake()->randomElement($data['types']);
        $typeName = $typeData['name'];
        $brand = fake()->randomElement($typeData['brands']);
        $name = "{$brand} {$typeName}";

        $price = fake()->numberBetween($typeData['price_range'][0], $typeData['price_range'][1]);
        $hasDiscount = fake()->boolean(15);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => "高品質{$typeName}，{$brand}出品。提升鍵盤手感與使用體驗的必備配件。",
            'content' => $this->generateAccessoriesContent($typeName, $brand),
            'sku' => 'AC-'.fake()->unique()->numerify('####-###'),
            'price' => $price,
            'original_price' => $hasDiscount ? round($price * 1.2) : null,
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 產生客製化套件資料
     */
    protected function generateCustomKits(): array
    {
        $data = self::$categoryProducts['custom-kits'];
        $brand = fake()->randomElement($data['brands']);
        $kits = $data['kits'][$brand] ?? ['Standard Kit'];
        $kit = fake()->randomElement($kits);
        $material = fake()->randomElement($data['materials']);
        $plate = fake()->randomElement($data['plates']);
        $name = "{$brand} {$kit} 客製化套件";

        $price = fake()->numberBetween($data['price_range'][0], $data['price_range'][1]);
        $hasDiscount = fake()->boolean(20);

        $layout = fake()->randomElement(['60%', '65%', '75%', '80% TKL']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => "{$brand} {$kit} 客製化鍵盤套件，{$material}外殼，{$layout} 配置。不含軸體與鍵帽，適合 DIY 愛好者。",
            'content' => $this->generateCustomKitsContent($brand, $kit, $material, $layout, $plate),
            'sku' => 'CK-'.fake()->unique()->numerify('####-###'),
            'price' => $price,
            'original_price' => $hasDiscount ? round($price * 1.25) : null,
            'stock' => fake()->numberBetween(0, 20),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 產生鍵盤詳細內容
     */
    protected function generateKeyboardContent(string $brand, string $model, string $switch, string $layout, string $connection): string
    {
        return "<h2>產品特色</h2><ul><li>品牌：{$brand}，型號：{$model}</li><li>軸體：{$switch}，提供優質打字體驗</li><li>配置：{$layout}，節省桌面空間</li><li>連接方式：{$connection}</li></ul><h2>產品說明</h2><p>{$brand} {$model} 是一款專為專業用戶設計的機械鍵盤。採用高品質 {$switch}，提供舒適的打字手感。{$layout} 配置既保留常用按鍵，又能有效節省桌面空間。</p><h2>包裝內容</h2><ul><li>鍵盤本體 x 1</li><li>USB-C 連接線 x 1</li><li>說明書 x 1</li><li>拔鍵器 x 1</li></ul>";
    }

    /**
     * 產生鍵帽詳細內容
     */
    protected function generateKeycapsContent(string $brand, string $theme, string $material, string $profile): string
    {
        return "<h2>產品特色</h2><ul><li>品牌：{$brand}</li><li>主題：{$theme}</li><li>材質：{$material}</li><li>高度：{$profile}</li></ul><h2>產品說明</h2><p>{$brand} {$theme} 鍵帽組採用高品質 {$material} 製作，{$profile} 高度設計符合人體工學。精美的 {$theme} 配色方案，讓您的鍵盤更具個人風格。</p><h2>相容性</h2><p>適用於 Cherry MX 軸體及相容軸體的機械鍵盤。</p>";
    }

    /**
     * 產生軸體詳細內容
     */
    protected function generateSwitchesContent(string $brand, string $type, string $switchType): string
    {
        return "<h2>產品特色</h2><ul><li>品牌：{$brand}</li><li>型號：{$type}</li><li>類型：{$switchType}</li></ul><h2>產品說明</h2><p>{$brand} {$type} 是一款高品質機械軸體，{$switchType}手感設計。適合追求極致打字體驗的玩家。可用於 DIY 客製化鍵盤或更換現有鍵盤軸體。</p><h2>使用建議</h2><p>建議搭配適當潤滑油使用，可獲得更順滑的手感體驗。</p>";
    }

    /**
     * 產生配件詳細內容
     */
    protected function generateAccessoriesContent(string $typeName, string $brand): string
    {
        return "<h2>產品特色</h2><ul><li>類型：{$typeName}</li><li>品牌/款式：{$brand}</li></ul><h2>產品說明</h2><p>高品質{$typeName}，能有效提升鍵盤的使用體驗。{$brand} 品質保證，是鍵盤愛好者的必備配件。</p>";
    }

    /**
     * 產生客製化套件詳細內容
     */
    protected function generateCustomKitsContent(string $brand, string $kit, string $material, string $layout, string $plate): string
    {
        return "<h2>產品特色</h2><ul><li>品牌：{$brand}</li><li>型號：{$kit}</li><li>材質：{$material}</li><li>配置：{$layout}</li><li>定位板：{$plate}</li></ul><h2>產品說明</h2><p>{$brand} {$kit} 是一款高品質客製化鍵盤套件。採用 {$material} 外殼，搭配 {$plate}，提供優異的打字手感與聲音表現。{$layout} 配置適合大多數使用場景。</p><h2>注意事項</h2><p>本產品為 Barebone 套件，不含軸體與鍵帽，需另行購買。適合有 DIY 經驗的玩家。</p><h2>包裝內容</h2><ul><li>外殼（上蓋 + 底殼）</li><li>PCB 電路板</li><li>定位板</li><li>穩定器組</li><li>USB-C 連接線</li><li>組裝說明書</li></ul>";
    }

    /**
     * 上架狀態
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * 下架狀態
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * 有庫存
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(10, 100),
        ]);
    }

    /**
     * 無庫存
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * 有折扣
     */
    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['price'] ?? 2000;

            return [
                'original_price' => $price * 1.3,
            ];
        });
    }
}
