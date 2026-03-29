<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理後台系統設定測試
 * 測試 /api/v1/admin/settings 端點的 CRUD 和權限控制
 */
class AdminSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * TC-SET-001: GET /admin/settings → 回傳所有設定
     * 驗證可以取得所有系統設定
     *
     * @test
     */
    public function can_fetch_all_settings(): void
    {
        // Arrange: 建立多個設定
        SystemSetting::factory()->create(['key' => 'site_name', 'group' => 'general']);
        SystemSetting::factory()->create(['key' => 'site_description', 'group' => 'general']);
        SystemSetting::factory()->create(['key' => 'ecpay_enabled', 'group' => 'payment']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'key',
                        'value',
                        'group',
                        'description',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * TC-SET-002: GET /admin/settings?group=payment → 只回傳 payment group
     * 驗證可以按 group 篩選設定
     *
     * @test
     */
    public function can_filter_settings_by_group(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'site_name', 'group' => 'general']);
        SystemSetting::factory()->create(['key' => 'site_description', 'group' => 'general']);
        SystemSetting::factory()->create(['key' => 'ecpay_enabled', 'group' => 'payment']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/settings?group=payment');

        // Assert: 只應回傳 payment 群組
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group', 'payment');
    }

    /**
     * TC-SET-003: PUT /admin/settings（合法 key）→ 200 更新成功
     * 驗證 super_admin 可以更新單一設定
     *
     * @test
     */
    public function super_admin_can_update_setting(): void
    {
        // Arrange: 建立設定
        SystemSetting::factory()->create(['key' => 'site_name', 'value' => 'Old Name']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'site_name',
                'value' => 'New Name',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.value', 'New Name');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'site_name',
            'value' => json_encode('New Name'),
        ]);
    }

    /**
     * TC-SET-004: PUT /admin/settings（不存在 key）→ 422
     * 驗證更新不存在的 key 會返回驗證錯誤
     *
     * @test
     */
    public function cannot_update_nonexistent_setting_key(): void
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'nonexistent_key',
                'value' => 'some_value',
            ]);

        // Assert: 應返回 422 驗證錯誤
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    /**
     * TC-SET-005: PUT /admin/settings/batch（多個設定）→ 200
     * 驗證可以批次更新多個設定
     *
     * @test
     */
    public function super_admin_can_batch_update_settings(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'site_name', 'value' => 'Old Name']);
        SystemSetting::factory()->create(['key' => 'site_description', 'value' => 'Old Desc']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings/batch', [
                'settings' => [
                    ['key' => 'site_name', 'value' => 'New Name'],
                    ['key' => 'site_description', 'value' => 'New Desc'],
                ],
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'site_name',
            'value' => json_encode('New Name'),
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'site_description',
            'value' => json_encode('New Desc'),
        ]);
    }

    /**
     * TC-SET-006: SystemSetting::getValue() 回傳正確值
     * 測試模型的 getValue() 靜態方法
     *
     * @test
     */
    public function get_value_returns_correct_value(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'test_key', 'value' => 'test_value']);

        // Act
        $value = SystemSetting::getValue('test_key');

        // Assert
        $this->assertEquals('test_value', $value);
    }

    /**
     * TC-SET-007: SystemSetting::getValue() 回傳預設值
     * 驗證 getValue() 在 key 不存在時回傳預設值
     *
     * @test
     */
    public function get_value_returns_default_when_key_not_found(): void
    {
        // Act
        $value = SystemSetting::getValue('nonexistent_key', 'default_value');

        // Assert
        $this->assertEquals('default_value', $value);
    }

    /**
     * TC-SET-008: SystemSetting::setValue() 新增設定
     * 驗證 setValue() 可以新增不存在的設定
     *
     * @test
     */
    public function set_value_creates_new_setting(): void
    {
        // Act
        SystemSetting::setValue('new_key', 'new_value', 'test_group', 'test description');

        // Assert
        $this->assertDatabaseHas('system_settings', [
            'key' => 'new_key',
            'value' => json_encode('new_value'),
            'group' => 'test_group',
            'description' => 'test description',
        ]);
    }

    /**
     * TC-SET-009: SystemSetting::setValue() 更新現有設定
     * 驗證 setValue() 可以更新已存在的設定
     *
     * @test
     */
    public function set_value_updates_existing_setting(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'existing_key', 'value' => 'old_value']);

        // Act
        SystemSetting::setValue('existing_key', 'updated_value');

        // Assert
        $this->assertDatabaseHas('system_settings', [
            'key' => 'existing_key',
            'value' => json_encode('updated_value'),
        ]);
    }

    /**
     * TC-SET-010: admin 嘗試修改設定 → 403
     * 驗證普通 admin 無法更新設定（只有 super_admin 可以）
     *
     * @test
     */
    public function admin_cannot_update_settings(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'site_name', 'value' => 'Old Name']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'site_name',
                'value' => 'New Name',
            ]);

        // Assert: 應被拒絕（未授權）
        $response->assertStatus(403);

        // 驗證值未被改變
        $this->assertDatabaseHas('system_settings', [
            'key' => 'site_name',
            'value' => json_encode('Old Name'),
        ]);
    }

    /**
     * TC-SET-011: value 注入 XSS payload → strip_tags 清除 HTML
     * 驗證包含 XSS payload 的設定值被 strip_tags 清除
     *
     * @test
     */
    public function xss_payload_in_string_setting_is_stripped(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'site_name', 'value' => 'Safe Name']);

        // Act: 嘗試注入 XSS
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'site_name',
                'value' => "Safe<script>alert('xss')</script>Name",
            ]);

        // Assert: 應成功但 HTML 標籤被移除
        $response->assertStatus(200);

        // 驗證資料庫中的值已被清除（strip_tags 移除所有標籤）
        $setting = SystemSetting::where('key', 'site_name')->first();
        // strip_tags 會移除 <script> 標籤但保留其中的文字
        $this->assertEquals("Safealert('xss')Name", $setting->value);
    }

    /**
     * TC-SET-012: boolean 型別驗證
     * 驗證 ecpay_enabled 必須是有效的布林值
     *
     * @test
     */
    public function boolean_setting_must_be_valid(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'ecpay_enabled', 'value' => true]);

        // Act: 嘗試設定無效布林值
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'ecpay_enabled',
                'value' => 'not_a_boolean',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    /**
     * TC-SET-013: 整數型別驗證
     * 驗證 shipping_fee 必須是非負整數
     *
     * @test
     */
    public function integer_setting_must_be_valid(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'shipping_fee', 'value' => 60]);

        // Act: 嘗試設定負數
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'shipping_fee',
                'value' => -100,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    /**
     * TC-SET-014: SystemSettingSeeder 重複執行不重複建立
     * 驗證 Seeder 執行兩次後，不會建立重複的設定
     *
     * @test
     */
    public function seeder_does_not_create_duplicates(): void
    {
        // Act: 執行 Seeder 第一次
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        $count1 = SystemSetting::count();

        // 執行 Seeder 第二次
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        $count2 = SystemSetting::count();

        // Assert: 計數應相同（updateOrCreate 確保不重複）
        $this->assertEquals($count1, $count2);
    }

    /**
     * TC-SET-015: 未認證使用者無法訪問
     *
     * @test
     */
    public function unauthenticated_user_cannot_access_settings(): void
    {
        // Act
        $response = $this->getJson('/api/v1/admin/settings');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * TC-SET-016: 普通使用者可存取 GET 設定（當前設計）
     * 注意：GET /admin/settings 未驗證管理員權限（只有 PUT 驗證）
     * 未來應添加權限檢查以限制非管理員存取
     *
     * @test
     */
    public function regular_user_can_currently_access_settings_index(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        // Assert: 當前設計允許任何認證使用者存取 GET
        $response->assertStatus(200);
    }

    /**
     * TC-SET-017: 設定鍵名不能為空
     *
     * @test
     */
    public function setting_key_is_required(): void
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'value' => 'some_value',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    /**
     * TC-SET-018: 設定值不能為空
     *
     * @test
     */
    public function setting_value_is_required(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'test_key']);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/v1/admin/settings', [
                'key' => 'test_key',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    /**
     * TC-SET-019: 批次更新驗證
     * 驗證批次更新端點也要驗證 super_admin 權限
     *
     * @test
     */
    public function batch_update_requires_super_admin(): void
    {
        // Arrange
        SystemSetting::factory()->create(['key' => 'site_name']);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/settings/batch', [
                'settings' => [
                    ['key' => 'site_name', 'value' => 'New Name'],
                ],
            ]);

        // Assert
        $response->assertStatus(403);
    }
}
