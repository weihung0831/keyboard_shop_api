<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    /**
     * @var array<string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * 取得設定值
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * 設定值（新增或更新）
     */
    public static function setValue(string $key, mixed $value, ?string $group = null, ?string $description = null): static
    {
        $data = ['value' => $value];

        if ($group !== null) {
            $data['group'] = $group;
        }

        if ($description !== null) {
            $data['description'] = $description;
        }

        return static::updateOrCreate(['key' => $key], $data);
    }
}
