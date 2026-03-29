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
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password123'),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        // 建立測試用戶
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

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
