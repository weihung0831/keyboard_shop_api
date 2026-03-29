<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理員認證與授權測試
 * TC-ADMIN-AUTH-001 ~ TC-ADMIN-AUTH-004
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-ADMIN-AUTH-001: 未登入存取 admin 路由 → 401
     * 驗證沒有認證令牌的請求被拒絕
     *
     * @test
     */
    public function unauthenticated_user_cannot_access_admin_routes(): void
    {
        // Act: 未帶 token 嘗試存取 admin 路由
        $response = $this->getJson('/api/v1/admin/products');

        // Assert: 應該回傳 401 未授權
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * TC-ADMIN-AUTH-002: role=user 建立商品（POST） → 403
     * 驗證普通用戶沒有管理員權限，透過 FormRequest authorize() 檢查
     *
     * @test
     */
    public function regular_user_cannot_create_product(): void
    {
        // Arrange: 建立普通用戶和分類
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
        ]);
        $category = \App\Models\ProductCategory::factory()->create();

        $payload = [
            'name' => 'Test Product',
            'category_id' => $category->id,
            'price' => 1000,
            'stock' => 10,
            'sku' => 'TEST-001',
        ];

        // Act: 使用普通用戶的 token 嘗試建立商品
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 應該回傳 403 禁止
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    /**
     * TC-ADMIN-AUTH-003: role=admin 建立商品 → 201
     * 驗證管理員可以建立商品
     *
     * @test
     */
    public function admin_user_can_create_product(): void
    {
        // Arrange: 建立管理員用戶和分類
        $admin = User::factory()->admin()->create();
        $category = \App\Models\ProductCategory::factory()->create();

        $payload = [
            'name' => 'Admin Product',
            'category_id' => $category->id,
            'price' => 2000,
            'stock' => 20,
            'sku' => 'ADMIN-001',
        ];

        // Act: 使用管理員 token 建立商品
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 應該成功，回傳 201
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Admin Product')
            ->assertJsonPath('data.sku', 'ADMIN-001');
    }

    /**
     * TC-ADMIN-AUTH-004: role=super_admin 建立商品 → 201
     * 驗證超級管理員可以建立商品
     *
     * @test
     */
    public function super_admin_user_can_create_product(): void
    {
        // Arrange: 建立超級管理員用戶和分類
        $superAdmin = User::factory()->superAdmin()->create();
        $category = \App\Models\ProductCategory::factory()->create();

        $payload = [
            'name' => 'Super Admin Product',
            'category_id' => $category->id,
            'price' => 3000,
            'stock' => 30,
            'sku' => 'SUPER-001',
        ];

        // Act: 使用超級管理員 token 建立商品
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/products', $payload);

        // Assert: 應該成功，回傳 201
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Super Admin Product')
            ->assertJsonPath('data.sku', 'SUPER-001');
    }
}
