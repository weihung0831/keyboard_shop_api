<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 管理員商品規格資源轉換器
 */
class ProductSpecificationResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 規格 ID
            'id' => $this->id,

            // 規格名稱
            'spec_name' => $this->spec_name,

            // 規格值
            'spec_value' => $this->spec_value,

            // 排序順序
            'sort_order' => $this->sort_order,
        ];
    }
}
