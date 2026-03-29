<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * 預設系統設定（冪等：updateOrCreate）
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => '鍵盤小舖',
                'group' => 'general',
                'description' => '網站名稱',
            ],
            [
                'key' => 'site_description',
                'value' => '專業機械鍵盤電商平台',
                'group' => 'general',
                'description' => '網站描述',
            ],
            [
                'key' => 'ecpay_enabled',
                'value' => true,
                'group' => 'payment',
                'description' => '是否啟用 ECPay 金流',
            ],
            [
                'key' => 'shipping_fee',
                'value' => 60,
                'group' => 'shipping',
                'description' => '標準運費（元）',
            ],
            [
                'key' => 'free_shipping_threshold',
                'value' => 1500,
                'group' => 'shipping',
                'description' => '免運門檻金額（元）',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'description' => $setting['description'],
                ]
            );
        }
    }
}
