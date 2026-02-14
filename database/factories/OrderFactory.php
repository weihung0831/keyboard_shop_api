<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $shipping_fee = 60;

        return [
            'user_id' => User::factory(),
            'order_number' => Order::generateOrderNumber(),
            'status' => Order::STATUS_PENDING,
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping_fee,
            'total_amount' => $subtotal + $shipping_fee,
            'shipping_name' => fake()->name(),
            'shipping_phone' => '09'.fake()->numerify('##-###-###'),
            'shipping_email' => fake()->email(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_city' => fake()->city(),
            'shipping_address' => fake()->address(),
            'shipping_method' => 'standard',
        ];
    }

    /**
     * 待付款狀態
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /**
     * 處理中狀態（已付款）
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PROCESSING,
            'paid_at' => now(),
        ]);
    }

    /**
     * 已出貨狀態
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_SHIPPED,
            'paid_at' => now()->subDays(2),
            'shipped_at' => now(),
        ]);
    }

    /**
     * 已完成狀態
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_COMPLETED,
            'paid_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(3),
            'completed_at' => now(),
        ]);
    }

    /**
     * 已取消狀態
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
