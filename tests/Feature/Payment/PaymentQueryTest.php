<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_query_own_order_payment()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->paid()->create(['order_id' => $order->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_id', 'status', 'amount', 'paid_at'],
            ]);
    }

    /** @test */
    public function cannot_query_others_order_payment()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);
        Payment::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment");

        // getOrderDetail 找不到 → message 含「不存在」→ 404
        $response->assertStatus(404);
    }

    /** @test */
    public function query_order_without_payment_returns_422()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/payment");

        $response->assertStatus(422);
    }

    /** @test */
    public function can_list_user_payments_paginated()
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $order = Order::factory()->create(['user_id' => $user->id]);
            Payment::factory()->paid()->create(['order_id' => $order->id]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/payments?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function empty_payment_list_for_new_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }
}
