<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_refund_paid_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')
                ->andReturn(['RtnCode' => '1', 'RtnMsg' => '退款成功']);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(200)
            ->assertJsonPath('message', '退款成功');

        $payment = Payment::first();
        $this->assertEquals(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertNotNull($payment->refund_amount);
    }

    /** @test */
    public function cannot_refund_unpaid_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_refund_already_refunded_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->refunded()->create([
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_refund_others_order()
    {
        $user = User::factory()->create();
        $other_user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $other_user->id]);
        Payment::factory()->paid()->create(['order_id' => $order->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_refund_without_trade_no()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => Payment::STATUS_PAID,
            'trade_no' => null,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => '付款記錄缺少綠界交易編號，無法退款，請聯繫客服']);
    }

    /** @test */
    public function handles_ecpay_refund_failure()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')
                ->andReturn(['RtnCode' => '0', 'RtnMsg' => '退款失敗']);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(502);
        $this->assertEquals(Payment::STATUS_PAID, Payment::first()->status);
    }

    /** @test */
    public function raw_refund_response_is_stored()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')
                ->andReturn(['RtnCode' => '1', 'RtnMsg' => '退款成功']);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund");

        $raw = Payment::first()->raw_refund_response;
        $this->assertIsArray($raw);
        $this->assertEquals('1', $raw['RtnCode']);
    }
}
