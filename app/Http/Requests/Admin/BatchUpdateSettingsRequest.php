<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 批次更新系統設定請求驗證
 * 僅 super_admin 可操作
 */
class BatchUpdateSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['required'],
        ];
    }

    /**
     * 附加動態型別驗證（依每個 key 決定對應 value 的規則）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $settings = $this->input('settings', []);

            if (! is_array($settings)) {
                return;
            }

            foreach ($settings as $index => $item) {
                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                if ($key === null || $value === null) {
                    continue;
                }

                $error = UpdateSettingRequest::validateSettingValue($key, $value);
                if ($error !== null) {
                    $v->errors()->add("settings.{$index}.value", $error);
                }
            }
        });
    }

    /**
     * 取得自訂錯誤訊息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => '請提供設定列表',
            'settings.array' => '設定列表格式不正確',
            'settings.min' => '至少需要提供一個設定項目',
            'settings.*.key.required' => '請提供設定鍵名',
            'settings.*.key.exists' => '指定的設定鍵名不存在',
            'settings.*.value.required' => '請提供設定值',
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

        if (is_array($data) && isset($data['settings'])) {
            foreach ($data['settings'] as &$item) {
                if (in_array($item['key'], ['site_name', 'site_description'], true)) {
                    $item['value'] = strip_tags((string) $item['value']);
                }
            }
            unset($item);
        }

        return $data;
    }
}
