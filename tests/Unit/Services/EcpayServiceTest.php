<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class EcpayServiceTest extends TestCase
{
    use RefreshDatabase;

    private function callBuildItemName(Order $order): string
    {
        $service = app(EcpayService::class);
        $method = new ReflectionMethod(EcpayService::class, 'buildItemName');

        return $method->invoke($service, $order);
    }

    /** @test */
    public function item_name_single_product()
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => '機械鍵盤',
            'quantity' => 1,
        ]);

        $result = $this->callBuildItemName($order);

        $this->assertEquals('機械鍵盤 x1', $result);
    }

    /** @test */
    public function item_name_multiple_products_separated_by_hash()
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => '機械鍵盤',
            'quantity' => 2,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => '鍵帽組',
            'quantity' => 1,
        ]);

        $result = $this->callBuildItemName($order);

        $this->assertStringContainsString('#', $result);
        $this->assertStringContainsString('機械鍵盤 x2', $result);
        $this->assertStringContainsString('鍵帽組 x1', $result);
    }
}
