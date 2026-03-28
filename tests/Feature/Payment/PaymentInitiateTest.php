<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInitiateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EcpayService::class, function ($mock) {
            $mock->shouldReceive('buildPaymentForm')
                ->andReturn('<form id="ecpay-checkout">mock payment form</form>');
        });
    }

    /** @test */
    public function can_initiate_payment_for_pending_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(200)
            ->assertJsonPath('message', '付款已建立，請前往綠界完成付款')
            ->assertJsonStructure([
                'data' => [
                    'payment' => ['id', 'merchant_trade_no', 'amount', 'status'],
                    'payment_html',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function cannot_initiate_payment_for_paid_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->processing()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_initiate_payment_for_cancelled_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->cancelled()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_initiate_payment_for_others_order()
    {
        $user = User::factory()->create();
        $other_user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $other_user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_initiate_payment_for_nonexistent_order()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders/99999/pay');

        $response->assertStatus(422);
    }

    /** @test */
    public function merchant_trade_no_format_is_valid()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertLessThanOrEqual(20, strlen($payment->merchant_trade_no));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $payment->merchant_trade_no);
        $this->assertStringStartsWith('KB', $payment->merchant_trade_no);
    }

    /** @test */
    public function total_amount_is_stored_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 1500.00,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $payment = Payment::first();
        $this->assertEquals(1500.00, (float) $payment->amount);
    }

    /** @test */
    public function can_re_initiate_pending_payment()
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay");

        $response->assertStatus(200);
        $this->assertDatabaseCount('payments', 1);
    }
}
