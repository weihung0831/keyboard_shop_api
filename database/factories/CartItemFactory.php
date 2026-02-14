<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'price' => fn (array $attributes) => Product::find($attributes['product_id'])?->price ?? fake()->randomFloat(2, 100, 5000),
        ];
    }

    /**
     * 指定數量
     */
    public function withQuantity(int $qty): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $qty,
        ]);
    }
}
