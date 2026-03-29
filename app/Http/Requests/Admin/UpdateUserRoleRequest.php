<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理員更新會員角色請求驗證
 */
class UpdateUserRoleRequest extends FormRequest
{
    /**
     * 判斷使用者是否有權限執行此請求
     * 只有 super_admin 可以變更角色
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
            'role' => ['required', 'string', 'in:user,admin,super_admin'],
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
            'role.required' => '請提供角色',
            'role.in' => '角色必須為 user、admin 或 super_admin',
        ];
    }
}
