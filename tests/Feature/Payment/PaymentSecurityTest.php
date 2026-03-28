<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function callback_accessible_without_auth()
    {
        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('verifyCallback')->andReturn(false);
        });

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => 'test',
        ]);

        // 應為 400（CheckMacValue 失敗），非 401
        $response->assertStatus(400);
    }

    /** @test */
    public function callback_rejects_get_request()
    {
        $response = $this->get('/api/v1/payments/callback');
        $response->assertStatus(405);
    }

    /** @test */
    public function config_reads_from_env()
    {
        $this->assertNotNull(config('ecpay.merchant_id'));
        $this->assertNotNull(config('ecpay.hash_key'));
        $this->assertNotNull(config('ecpay.hash_iv'));
    }

    /** @test */
    public function initiate_payment_requires_auth()
    {
        $response = $this->postJson('/api/v1/orders/1/pay');
        $response->assertStatus(401);
    }

    /** @test */
    public function refund_requires_auth()
    {
        $response = $this->postJson('/api/v1/orders/1/refund');
        $response->assertStatus(401);
    }

    /** @test */
    public function cancel_paid_order_triggers_refund()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')
                ->once()
                ->andReturn(['RtnCode' => '1', 'RtnMsg' => '退款成功']);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertEquals(Payment::STATUS_REFUNDED, Payment::first()->status);
    }
}
