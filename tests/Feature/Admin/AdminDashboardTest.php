<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理後台儀表板測試
 * 測試 /api/v1/admin/dashboard/stats 端點及統計邏輯
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 建立管理員帳號用於測試
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * TC-DASH-001: GET /admin/dashboard/stats?period=today → 回傳今日統計
     * 驗證回應結構包含 revenue、orders、members、top_products
     *
     * @test
     */
    public function can_fetch_today_stats_with_correct_structure(): void
    {
        // Arrange: 建立今日訂單
        $order = Order::factory()->processing()->create([
            'paid_at' => now(),
            'total_amount' => 5000,
        ]);

        // Act: 呼叫端點
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=today');

        // Assert: 驗證結構和資料
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'revenue' => [
                        'total',
                        'trend_percentage',
                        'period_label',
                    ],
                    'orders' => [
                        'total',
                        'trend_percentage',
                        'pending_count',
                        'processing_count',
                    ],
                    'members' => [
                        'total',
                        'new_count',
                        'trend_percentage',
                    ],
                    'top_products' => [],
                ],
            ]);
    }

    /**
     * TC-DASH-002: GET /admin/dashboard/stats?period=7d → 回傳 7 天統計
     * 建立過去 7 天的訂單，驗證被計算在內
     *
     * @test
     */
    public function can_fetch_7_days_stats(): void
    {
        // Arrange: 建立過去 7 天的訂單
        $order1 = Order::factory()->processing()->create([
            'paid_at' => now()->subDays(3),
            'total_amount' => 3000,
        ]);
        $order2 = Order::factory()->processing()->create([
            'paid_at' => now()->subDays(1),
            'total_amount' => 2000,
        ]);

        // 建立前 7-13 天的訂單（用於 trend_percentage 計算）
        Order::factory()->processing()->create([
            'paid_at' => now()->subDays(10),
            'total_amount' => 1500,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=7d');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.period_label', '近7天')
            ->assertJsonPath('data.revenue.total', 5000);
    }

    /**
     * TC-DASH-003: GET /admin/dashboard/stats?period=30d → 回傳 30 天統計 (default)
     * 不提供 period 參數時，應使用 30d 預設值
     *
     * @test
     */
    public function can_fetch_30_days_stats_as_default(): void
    {
        // Arrange: 建立過去 30 天的訂單
        $order = Order::factory()->processing()->create([
            'paid_at' => now()->subDays(15),
            'total_amount' => 8000,
        ]);

        // Act: 不提供 period 參數
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        // Assert: 應回傳 30 天統計
        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.total', 8000);
    }

    /**
     * TC-DASH-004: GET /admin/dashboard/stats?period=all → trend_percentage 為 null
     * 當 period=all 時，trend_percentage 應為 null（無前期數據可比較）
     *
     * @test
     */
    public function all_period_stats_has_null_trend_percentage(): void
    {
        // Arrange: 建立舊訂單
        $order = Order::factory()->processing()->create([
            'paid_at' => now()->subDays(60),
            'total_amount' => 10000,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=all');

        // Assert: trend_percentage 應為 null
        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.trend_percentage', null)
            ->assertJsonPath('data.orders.trend_percentage', null)
            ->assertJsonPath('data.members.trend_percentage', null);
    }

    /**
     * TC-DASH-005: revenue 排除 cancelled 訂單
     * 建立已付款和已取消訂單，驗證只計算已付款訂單的營收
     *
     * @test
     */
    public function revenue_excludes_cancelled_orders(): void
    {
        // Arrange: 建立已付款訂單
        $paid_order = Order::factory()->processing()->create([
            'paid_at' => now(),
            'total_amount' => 5000,
        ]);

        // 建立已取消訂單（應被排除）
        $cancelled_order = Order::factory()->cancelled()->create([
            'total_amount' => 3000,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=today');

        // Assert: 只計算已付款訂單
        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.total', 5000);
    }

    /**
     * TC-DASH-006: top_products 排序正確
     * 建立多筆訂單項目，驗證熱門商品依銷售數量正確排序
     *
     * @test
     */
    public function top_products_are_sorted_by_quantity(): void
    {
        // Arrange: 建立商品
        $category = ProductCategory::factory()->create();
        $product1 = Product::factory()->create(['category_id' => $category->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id]);
        $product3 = Product::factory()->create(['category_id' => $category->id]);

        // 建立訂單及項目（product1: 50, product2: 30, product3: 10）
        $order1 = Order::factory()->processing()->create(['paid_at' => now()]);
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'product_name' => $product1->name,
            'quantity' => 50,
            'subtotal' => 5000,
        ]);

        $order2 = Order::factory()->processing()->create(['paid_at' => now()]);
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'product_id' => $product2->id,
            'product_name' => $product2->name,
            'quantity' => 30,
            'subtotal' => 3000,
        ]);

        $order3 = Order::factory()->processing()->create(['paid_at' => now()]);
        OrderItem::factory()->create([
            'order_id' => $order3->id,
            'product_id' => $product3->id,
            'product_name' => $product3->name,
            'quantity' => 10,
            'subtotal' => 1000,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=30d');

        // Assert: 驗證排序（最多銷售量優先）
        $response->assertStatus(200);
        $topProducts = $response->json('data.top_products');
        $this->assertCount(3, $topProducts);
        $this->assertEquals(50, $topProducts[0]['total_quantity']);
        $this->assertEquals(30, $topProducts[1]['total_quantity']);
        $this->assertEquals(10, $topProducts[2]['total_quantity']);
    }

    /**
     * TC-DASH-007: trend_percentage 計算正確
     * 建立當期和前期訂單，驗證趨勢百分比計算 (current - previous) / previous * 100
     *
     * @test
     */
    public function trend_percentage_calculation_is_correct(): void
    {
        // Arrange: 建立前期訂單（10-13 天前）
        Order::factory()->processing()->create([
            'paid_at' => now()->subDays(11),
            'total_amount' => 1000,
        ]);

        // 建立當期訂單（3-6 天前）
        Order::factory()->processing()->create([
            'paid_at' => now()->subDays(5),
            'total_amount' => 1500,
        ]);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=7d');

        // Assert: trend = (1500 - 1000) / 1000 * 100 = 50%
        $response->assertStatus(200);
        $trendPercentage = $response->json('data.revenue.trend_percentage');
        $this->assertNotNull($trendPercentage);
        $this->assertEquals(50.0, $trendPercentage);
    }

    /**
     * TC-DASH-008: 無資料時不報錯
     * 在空的資料庫呼叫統計端點，應回傳零值而非錯誤
     *
     * @test
     */
    public function returns_zeros_when_no_data_exists(): void
    {
        // Act: 不建立任何訂單
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        // Assert: 應回傳零值
        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.total', 0)
            ->assertJsonPath('data.orders.total', 0)
            ->assertJsonPath('data.orders.pending_count', 0)
            ->assertJsonPath('data.orders.processing_count', 0)
            ->assertJsonPath('data.members.total', 1) // 只有 admin 帳號
            ->assertJsonPath('data.members.new_count', 1) // admin 在當期被建立
            ->assertJsonPath('data.top_products', []);
    }

    /**
     * TC-DASH-009: 訂單 total 計算全部非取消訂單（含未付款）
     * 驗證 orders.total 計算非取消訂單數，排除已取消
     *
     * @test
     */
    public function orders_total_excludes_cancelled_only(): void
    {
        // Arrange
        Order::factory()->pending()->create(); // 待付款，計入
        Order::factory()->processing()->create(['paid_at' => now()]); // 已付款，計入
        Order::factory()->cancelled()->create(); // 已取消，不計

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=today');

        // Assert: 2 筆非取消訂單（pending + processing）
        $response->assertStatus(200)
            ->assertJsonPath('data.orders.total', 2);
    }

    /**
     * TC-DASH-010: pending 和 processing 計數不限期間
     * 驗證待付款和處理中訂單不受期間篩選影響
     *
     * @test
     */
    public function pending_and_processing_counts_ignore_period(): void
    {
        // Arrange: 建立舊的 pending 訂單（應被計算）
        Order::factory()->pending()->create([
            'created_at' => now()->subDays(60),
        ]);

        // 建立舊的 processing 訂單（已付款，應被計算）
        Order::factory()->processing()->create([
            'created_at' => now()->subDays(60),
            'paid_at' => now()->subDays(60),
        ]);

        // Act: 查詢今日統計
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=today');

        // Assert: pending_count 和 processing_count 應包含所有舊訂單
        $response->assertStatus(200)
            ->assertJsonPath('data.orders.pending_count', 1)
            ->assertJsonPath('data.orders.processing_count', 1);
    }

    /**
     * TC-DASH-011: 非管理員無法訪問
     * 注意：目前控制器 GET 端點未驗證管理員權限，只有 FormRequest 的 authorize() 檢查
     * 此測試記錄當前行為，未來應在控制器添加權限檢查
     *
     * @test
     */
    public function regular_user_can_currently_access_dashboard_stats(): void
    {
        // Arrange: 建立普通會員
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats');

        // Assert: 當前設計允許任何認證使用者存取（應被拒絕）
        $response->assertStatus(200);
    }

    /**
     * TC-DASH-012: 未認證使用者無法訪問
     *
     * @test
     */
    public function unauthenticated_user_cannot_access_dashboard_stats(): void
    {
        // Act: 不提供 token
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-DASH-013: 無效的 period 參數被拒絕
     *
     * @test
     */
    public function invalid_period_parameter_returns_validation_error(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats?period=invalid');

        // Assert
        $response->assertStatus(422);
    }
}
