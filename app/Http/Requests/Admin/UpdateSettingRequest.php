<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 更新單一系統設定請求驗證
 * 僅 super_admin 可操作
 */
class UpdateSettingRequest extends FormRequest
{
    /**
     * 僅超級管理員可執行此請求
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'exists:system_settings,key'],
            'value' => ['required'],
        ];
    }

    /**
     * 附加動態型別驗證（依 key 決定 value 的規則）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $key = $this->input('key');
            $value = $this->input('value');

            if ($key === null || $value === null) {
                return;
            }

            $error = $this->validateSettingValue($key, $value);
            if ($error !== null) {
                $v->errors()->add('value', $error);
            }
        });
    }

    /**
     * 動態驗證設定值並回傳錯誤訊息（null 表示通過）
     */
    public static function validateSettingValue(string $key, mixed $value): ?string
    {
        return match ($key) {
            'ecpay_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null
                ? '欄位 ecpay_enabled 必須為布林值'
                : null,

            'shipping_fee', 'free_shipping_threshold' => (function () use ($key, $value): ?string {
                if (! is_numeric($value) || (int) $value < 0 || (int) $value != $value) {
                    return "欄位 {$key} 必須為非負整數";
                }

                return null;
            })(),

            'site_name', 'site_description' => (function () use ($key, $value): ?string {
                if (! is_string($value)) {
                    return "欄位 {$key} 必須為字串";
                }
                if (mb_strlen($value) > 255) {
                    return "欄位 {$key} 不可超過 255 個字元";
                }

                return null;
            })(),

            default => null,
        };
    }

    /**
     * 取得自訂錯誤訊息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => '請提供設定鍵名',
            'key.exists' => '指定的設定鍵名不存在',
            'value.required' => '請提供設定值',
        ];
    }

    /**
     * 通過驗證後對 string 類型設定套用 strip_tags()
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if (is_array($data) && isset($data['key'], $data['value'])) {
            if (in_array($data['key'], ['site_name', 'site_description'], true)) {
                $data['value'] = strip_tags((string) $data['value']);
            }
        }

        return $data;
    }
}
