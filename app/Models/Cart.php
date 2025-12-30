<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
    ];

    /**
     * 取得購物車所屬會員
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 取得購物車的所有項目
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Scope: 查詢會員購物車
     */
    public function scopeForUser($query, int $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    /**
     * Scope: 查詢訪客購物車
     */
    public function scopeForSession($query, string $session_id)
    {
        return $query->where('session_id', $session_id);
    }

    /**
     * 計算購物車總金額
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->subtotal;
        });
    }

    /**
     * 計算購物車總項目數
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }
}
