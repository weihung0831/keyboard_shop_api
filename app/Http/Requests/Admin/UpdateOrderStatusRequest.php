<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員更新訂單狀態請求驗證
 */
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * 取得驗證規則
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:processing,shipped,completed,cancelled'],
        ];
    }

    /**
     * 取得自訂錯誤訊息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => '請提供訂單狀態',
            'status.in' => '訂單狀態必須為 processing、shipped、completed 或 cancelled',
        ];
    }
}
