<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理員會員管理 API 測試
 * 測試列表、搜尋、篩選、詳情查詢與角色管理功能
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 建立超級管理員
        $this->superAdmin = User::factory()->superAdmin()->create();
        // 建立普通管理員
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * TC-ADMIN-USER-001: GET /admin/users → 回傳含 orders_count，分頁
     *
     * @test
     */
    public function can_list_all_users_with_orders_count()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Order::factory()->count(3)->create(['user_id' => $user1->id]);
        Order::factory()->count(5)->create(['user_id' => $user2->id]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '取得會員列表成功')
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'orders_count',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                    'last_page',
                ],
            ]);

        // 驗證 orders_count 正確
        $data = $response->json('data');
        $user1Data = collect($data)->firstWhere('id', $user1->id);
        $user2Data = collect($data)->firstWhere('id', $user2->id);

        $this->assertEquals(3, $user1Data['orders_count']);
        $this->assertEquals(5, $user2Data['orders_count']);
    }

    /**
     * TC-ADMIN-USER-002: GET /admin/users?search=test → 搜尋姓名
     *
     * @test
     */
    public function can_search_users_by_name()
    {
        // Arrange
        User::factory()->create(['name' => 'Wang Ming']);
        User::factory()->create(['name' => 'Lee Hua']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=Wang');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wang Ming');
    }

    /**
     * TC-ADMIN-USER-003: GET /admin/users?search=email → 搜尋 Email
     *
     * @test
     */
    public function can_search_users_by_email()
    {
        // Arrange
        User::factory()->create(['email' => 'test@example.com']);
        User::factory()->create(['email' => 'user@example.com']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=test@example.com');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'test@example.com');
    }

    /**
     * TC-ADMIN-USER-004: GET /admin/users?role=admin → 只回傳 admin
     *
     * @test
     */
    public function can_filter_users_by_role()
    {
        // Arrange
        User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'user']);
        $newAdmin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?role=admin');

        // Assert
        $response->assertStatus(200)
            // setUp creates $this->admin, so we should have at least 2 admins
            ->assertJsonCount(2, 'data');

        // Verify all returned are admin role
        foreach ($response->json('data') as $user) {
            $this->assertEquals('admin', $user['role']);
        }
    }

    /**
     * TC-ADMIN-USER-005: GET /admin/users/{id} → 含訂單統計 + 最近 10 筆購買記錄
     *
     * @test
     */
    public function can_get_user_detail_with_order_stats_and_recent_orders()
    {
        // Arrange
        $user = User::factory()->create();

        // 建立 12 筆訂單
        Order::factory()->count(12)->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '取得會員詳情成功')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                    'order_stats' => [
                        'total_count',
                        'total_spent',
                        'by_status',
                    ],
                    'recent_orders',
                ],
            ]);

        // 驗證訂單統計
        $data = $response->json('data');
        $this->assertEquals(12, $data['order_stats']['total_count']);

        // 驗證最近 10 筆訂單（最多回傳 10 筆）
        $this->assertCount(10, $data['recent_orders']);
        $this->assertLessThanOrEqual(10, count($data['recent_orders']));
    }

    /**
     * TC-ADMIN-USER-006: GET /admin/users/{id} → 訂單統計含各狀態計數
     *
     * @test
     */
    public function user_detail_contains_order_status_breakdown()
    {
        // Arrange
        $user = User::factory()->create();

        Order::factory()->pending()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->processing()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->shipped()->count(1)->create(['user_id' => $user->id]);
        Order::factory()->completed()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->cancelled()->count(1)->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $data = $response->json('data');
        $byStatus = $data['order_stats']['by_status'];

        $this->assertEquals(2, $byStatus[Order::STATUS_PENDING]);
        $this->assertEquals(3, $byStatus[Order::STATUS_PROCESSING]);
        $this->assertEquals(1, $byStatus[Order::STATUS_SHIPPED]);
        $this->assertEquals(2, $byStatus[Order::STATUS_COMPLETED]);
        $this->assertEquals(1, $byStatus[Order::STATUS_CANCELLED]);
    }

    /**
     * TC-ADMIN-USER-007: GET /admin/users/{id} → 只計算已完成交易的金額
     *
     * @test
     */
    public function user_detail_total_spent_only_includes_completed_transactions()
    {
        // Arrange
        $user = User::factory()->create();

        // pending 訂單（不計入）
        Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 1000,
        ]);

        // processing 訂單（計入）
        Order::factory()->processing()->create([
            'user_id' => $user->id,
            'total_amount' => 2000,
        ]);

        // completed 訂單（計入）
        Order::factory()->completed()->create([
            'user_id' => $user->id,
            'total_amount' => 3000,
        ]);

        // cancelled 訂單（不計入）
        Order::factory()->cancelled()->create([
            'user_id' => $user->id,
            'total_amount' => 500,
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $totalSpent = $response->json('data.order_stats.total_spent');
        // processing (2000) + shipped (0) + completed (3000) = 5000
        $this->assertEquals(5000, $totalSpent);
    }

    /**
     * TC-ADMIN-USER-008: super_admin PATCH /admin/users/{id}/role → admin → 200
     *
     * @test
     */
    public function super_admin_can_update_user_role()
    {
        // Arrange
        $targetUser = User::factory()->create(['role' => 'user']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'admin',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('message', '會員角色已更新')
            ->assertJsonPath('data.role', 'admin');

        $this->assertEquals('admin', $targetUser->fresh()->role);
    }

    /**
     * TC-ADMIN-USER-009: super_admin 不可修改自己的角色 → 422
     *
     * @test
     */
    public function super_admin_cannot_change_own_role()
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$this->superAdmin->id}/role", [
                'role' => 'user',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonPath('message', '不可修改自身角色');

        $this->assertEquals('super_admin', $this->superAdmin->fresh()->role);
    }

    /**
     * TC-ADMIN-USER-010: admin（非 super_admin）不可修改角色 → 403
     *
     * @test
     */
    public function admin_cannot_update_user_role()
    {
        // Arrange
        $targetUser = User::factory()->create(['role' => 'user']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'admin',
            ]);

        // Assert
        // UpdateUserRoleRequest checks authorize() which uses isSuperAdmin()
        $response->assertStatus(403);

        $this->assertEquals('user', $targetUser->fresh()->role);
    }

    /**
     * TC-ADMIN-USER-011: 修改不存在的會員 → 404
     *
     * @test
     */
    public function update_nonexistent_user_returns_404()
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson('/api/v1/admin/users/99999/role', [
                'role' => 'admin',
            ]);

        // Assert
        $response->assertStatus(404)
            ->assertJsonPath('message', '會員不存在');
    }

    /**
     * TC-ADMIN-USER-012: 無認證 → 401
     *
     * @test
     */
    public function cannot_list_users_without_authentication()
    {
        // Act
        $response = $this->getJson('/api/v1/admin/users');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-ADMIN-USER-013: 普通用戶（非管理員）不可修改角色 → 403
     *
     * @test
     */
    public function regular_user_cannot_update_user_role()
    {
        // Arrange
        $regularUser = User::factory()->create();
        $targetUser = User::factory()->create();

        // Act
        $response = $this->actingAs($regularUser, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'admin',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * TC-ADMIN-USER-014: 查詢不存在的會員 → 404
     *
     * @test
     */
    public function get_nonexistent_user_returns_404()
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users/99999');

        // Assert
        $response->assertStatus(404)
            ->assertJsonPath('message', '會員不存在');
    }

    /**
     * TC-ADMIN-USER-015: 會員列表分頁正確性
     *
     * @test
     */
    public function user_pagination_works_correctly()
    {
        // Arrange
        User::factory()->count(20)->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?per_page=10');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10);
        // 總數包含 superAdmin 和 admin
        $this->assertGreaterThanOrEqual(20, $response->json('meta.total'));
    }

    /**
     * TC-ADMIN-USER-016: 搜尋結果分頁正確性
     *
     * @test
     */
    public function user_search_results_are_paginated()
    {
        // Arrange
        for ($i = 0; $i < 15; $i++) {
            User::factory()->create(['name' => "Test User {$i}"]);
        }

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=Test User&per_page=5');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    /**
     * TC-ADMIN-USER-017: 複合篩選：搜尋 + 角色篩選
     *
     * @test
     */
    public function can_filter_users_with_combined_criteria()
    {
        // Arrange
        User::factory()->create([
            'name' => 'Wang Ming',
            'role' => 'user',
        ]);
        User::factory()->admin()->create([
            'name' => 'Wang Hua',
        ]);
        User::factory()->admin()->create([
            'name' => 'Lee Ming',
        ]);

        // Act - 搜尋名字包含「Wang」且角色為 admin
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=Wang&role=admin');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wang Hua');
    }

    /**
     * TC-ADMIN-USER-018: super_admin 可升級 user 為 super_admin
     *
     * @test
     */
    public function super_admin_can_promote_user_to_super_admin()
    {
        // Arrange
        $targetUser = User::factory()->create(['role' => 'user']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$targetUser->id}/role", [
                'role' => 'super_admin',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'super_admin');

        $this->assertEquals('super_admin', $targetUser->fresh()->role);
    }

    /**
     * TC-ADMIN-USER-019: 降級 super_admin 為 admin
     *
     * @test
     */
    public function super_admin_can_downgrade_another_super_admin()
    {
        // Arrange
        $targetSuperAdmin = User::factory()->superAdmin()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$targetSuperAdmin->id}/role", [
                'role' => 'admin',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'admin');

        $this->assertEquals('admin', $targetSuperAdmin->fresh()->role);
    }

    /**
     * TC-ADMIN-USER-020: 最近訂單按建立時間倒序
     *
     * @test
     */
    public function recent_orders_are_sorted_by_created_at_descending()
    {
        // Arrange
        $user = User::factory()->create();

        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);
        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(3),
        ]);
        $order3 = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $recentOrders = $response->json('data.recent_orders');
        $this->assertEquals($order3->id, $recentOrders[0]['id']);
        $this->assertEquals($order2->id, $recentOrders[1]['id']);
        $this->assertEquals($order1->id, $recentOrders[2]['id']);
    }

    /**
     * TC-ADMIN-USER-021: 角色篩選多個角色類型
     *
     * @test
     */
    public function can_filter_all_user_roles_separately()
    {
        // Arrange
        User::factory()->count(5)->create(['role' => 'user']);
        User::factory()->count(3)->admin()->create();
        User::factory()->count(2)->superAdmin()->create();
        // setUp() creates $this->superAdmin and $this->admin, so totals will be +1 super_admin and +1 admin

        // Act - Filter by user role
        $userResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?role=user');

        // Act - Filter by admin role
        $adminResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?role=admin');

        // Act - Filter by super_admin role
        $superAdminResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/users?role=super_admin');

        // Assert
        $userResponse->assertStatus(200)
            ->assertJsonCount(5, 'data');

        // setUp creates $this->admin, so count is 3 + 1 = 4
        $adminResponse->assertStatus(200)
            ->assertJsonCount(4, 'data');

        // setUp creates $this->superAdmin, so count is 2 + 1 = 3
        $superAdminResponse->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * TC-ADMIN-USER-022: 無訂單的會員，訂單統計為 0
     *
     * @test
     */
    public function user_with_no_orders_shows_zero_stats()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $orderStats = $response->json('data.order_stats');
        $this->assertEquals(0, $orderStats['total_count']);
        $this->assertEquals(0, $orderStats['total_spent']);
        $this->assertCount(0, $response->json('data.recent_orders'));
    }

    /**
     * TC-ADMIN-USER-023: 會員資訊包含建立時間
     *
     * @test
     */
    public function user_detail_includes_created_at_timestamp()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}");

        // Assert
        $response->assertJsonPath('data.created_at', fn ($val) => ! empty($val));
        $this->assertNotNull($response->json('data.created_at'));
    }
}
