<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'merchant_trade_no' => 'KB'.fake()->unique()->numerify('####').now()->format('YmdHis'),
            'trade_no' => null,
            'payment_method' => Payment::METHOD_CREDIT,
            'amount' => fake()->randomFloat(2, 100, 10000),
            'status' => Payment::STATUS_PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_PAID,
            'trade_no' => fake()->numerify('##########'),
            'paid_at' => now(),
            'raw_callback' => ['RtnCode' => '1', 'RtnMsg' => '交易成功'],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_FAILED,
            'raw_callback' => ['RtnCode' => '0', 'RtnMsg' => '交易失敗'],
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_REFUNDED,
            'trade_no' => fake()->numerify('##########'),
            'paid_at' => now()->subDay(),
            'refunded_at' => now(),
            'refund_amount' => fn (array $attrs) => $attrs['amount'],
            'raw_refund_response' => ['RtnCode' => '1'],
        ]);
    }
}
