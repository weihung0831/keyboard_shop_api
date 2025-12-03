<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * 各分類的圖片 URL（使用 Unsplash 真實圖片）
     */
    protected static array $categoryImages = [
        'mechanical-keyboards' => [
            'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1595225476474-87563907a212?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1601445638532-3c6f6c3aa1d6?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1561112078-7d24e04c3407?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1558050032-160f36233a07?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1563191911-e65f8655ebf9?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?w=800&h=600&fit=crop',
        ],
        'keycaps' => [
            'https://images.unsplash.com/photo-1595044426077-d36d9236d54a?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1558050032-160f36233a07?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1563191911-e65f8655ebf9?w=800&h=600&fit=crop',
        ],
        'switches' => [
            'https://images.unsplash.com/photo-1595225476474-87563907a212?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1558050032-160f36233a07?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=800&h=600&fit=crop',
        ],
        'keyboard-accessories' => [
            'https://images.unsplash.com/photo-1558050032-160f36233a07?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1595225476474-87563907a212?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=600&fit=crop',
        ],
        'custom-kits' => [
            'https://images.unsplash.com/photo-1595044426077-d36d9236d54a?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1595225476474-87563907a212?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1561112078-7d24e04c3407?w=800&h=600&fit=crop',
            'https://images.unsplash.com/photo-1601445638532-3c6f6c3aa1d6?w=800&h=600&fit=crop',
        ],
    ];

    /**
     * 當前使用的分類 slug
     */
    protected ?string $currentCategorySlug = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'image_url' => $this->getRandomImage(),
            'is_primary' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * 設定分類以取得對應的圖片
     */
    public function forCategory(string $slug): static
    {
        return $this->state(function (array $attributes) use ($slug) {
            return [
                'image_url' => $this->getRandomImage($slug),
            ];
        });
    }

    /**
     * 取得隨機圖片 URL
     */
    protected function getRandomImage(?string $categorySlug = null): string
    {
        $slug = $categorySlug ?? $this->currentCategorySlug ?? 'mechanical-keyboards';
        $images = self::$categoryImages[$slug] ?? self::$categoryImages['mechanical-keyboards'];

        return fake()->randomElement($images);
    }

    /**
     * 設為主圖
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * 設定排序順序
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
