<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 產品圖片資源轉換器
 */
class ProductImageResource extends JsonResource
{
    /**
     * 將資源轉換為陣列
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 圖片 ID
            'id' => $this->id,

            // 圖片 URL
            'url' => $this->image_url,

            // 是否為主圖
            'is_primary' => (bool) $this->is_primary,

            // 排序順序
            'sort_order' => $this->sort_order,
        ];
    }
}
