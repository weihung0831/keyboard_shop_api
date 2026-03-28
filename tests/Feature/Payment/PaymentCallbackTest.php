<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingPayment(): Payment
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        return Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
        ]);
    }

    private function mockVerifyCallback(bool $result = true): void
    {
        $this->mock(EcpayService::class, function ($mock) use ($result) {
            $mock->shouldReceive('verifyCallback')->andReturn($result);
        });
    }

    /** @test */
    public function valid_callback_marks_payment_as_paid()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(true);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
            'RtnMsg' => '交易成功',
            'TradeNo' => '2026032912345678',
            'SimulatePaid' => '0',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('1|OK', $response->getContent());

        $payment->refresh();
        $this->assertEquals(Payment::STATUS_PAID, $payment->status);
        $this->assertEquals('2026032912345678', $payment->trade_no);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->raw_callback);

        $this->assertEquals(Order::STATUS_PROCESSING, $payment->order->fresh()->status);
    }

    /** @test */
    public function invalid_check_mac_value_returns_400()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(false);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    /** @test */
    public function failed_payment_callback()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(true);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '10100058',
            'RtnMsg' => '付款失敗',
            'SimulatePaid' => '0',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('1|OK', $response->getContent());
        $this->assertEquals(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertEquals(Order::STATUS_PENDING, $payment->order->fresh()->status);
    }

    /** @test */
    public function duplicate_callback_is_idempotent()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->paid()->create([
            'order_id' => $order->id,
        ]);
        $this->mockVerifyCallback(true);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
            'SimulatePaid' => '0',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('1|OK', $response->getContent());
        $this->assertEquals(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    /** @test */
    public function simulated_paid_does_not_update_order()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(true);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
            'TradeNo' => '2026032912345678',
            'SimulatePaid' => '1',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('1|OK', $response->getContent());
        $this->assertNotEquals(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals(Order::STATUS_PENDING, $payment->order->fresh()->status);
    }

    /** @test */
    public function unknown_merchant_trade_no_returns_ok()
    {
        $this->mockVerifyCallback(true);

        $response = $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => 'UNKNOWN123',
            'RtnCode' => '1',
            'SimulatePaid' => '0',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('1|OK', $response->getContent());
    }

    /** @test */
    public function trade_no_is_stored_from_callback()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(true);

        $ecpay_trade_no = '2026032987654321';
        $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
            'TradeNo' => $ecpay_trade_no,
            'SimulatePaid' => '0',
        ]);

        $this->assertEquals($ecpay_trade_no, $payment->fresh()->trade_no);
    }

    /** @test */
    public function raw_callback_is_stored()
    {
        $payment = $this->createPendingPayment();
        $this->mockVerifyCallback(true);

        $this->post('/api/v1/payments/callback', [
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'RtnCode' => '1',
            'RtnMsg' => '交易成功',
            'TradeNo' => '2026032912345678',
            'SimulatePaid' => '0',
        ]);

        $raw = $payment->fresh()->raw_callback;
        $this->assertIsArray($raw);
        $this->assertEquals('1', $raw['RtnCode']);
    }
}
