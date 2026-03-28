<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_CREDIT = 'Credit';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => '待付款',
        self::STATUS_PAID => '已付款',
        self::STATUS_FAILED => '付款失敗',
        self::STATUS_REFUNDED => '已退款',
    ];

    protected $fillable = [
        'order_id',
        'merchant_trade_no',
        'trade_no',
        'payment_method',
        'amount',
        'status',
        'paid_at',
        'refunded_at',
        'refund_amount',
        'raw_callback',
        'raw_refund_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'raw_callback' => 'array',
            'raw_refund_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForOrder($query, int $order_id)
    {
        return $query->where('order_id', $order_id);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, int $user_id)
    {
        return $query->whereHas('order', fn ($q) => $q->where('user_id', $user_id));
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * 產生 MerchantTradeNo
     * 格式：KB{order_id}{YmdHis}，最多 20 字元
     */
    public static function generateMerchantTradeNo(int $order_id): string
    {
        $timestamp = now()->format('YmdHis');
        $trade_no = 'KB'.$order_id.$timestamp;

        return substr($trade_no, 0, 20);
    }
}
