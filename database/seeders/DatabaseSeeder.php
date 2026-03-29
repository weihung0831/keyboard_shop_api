<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 開始建立測試資料...');

        // 建立管理員帳號
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);

        // 建立測試用戶
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        User::factory(49)->create();

        $this->command->info('✅ 已建立 50 個測試用戶');

        // 建立商品分類
        $this->call([
            ProductCategorySeeder::class,
            ProductSeeder::class,
        ]);

        $this->command->info('🎉 所有測試資料建立完成！');
    }
}
