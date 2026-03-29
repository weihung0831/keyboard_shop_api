<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /**
     * 訂單狀態常數
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * 訂單狀態標籤對應
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => '待處理',
        self::STATUS_PROCESSING => '處理中',
        self::STATUS_SHIPPED => '已出貨',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_CANCELLED => '已取消',
    ];

    /**
     * 可批量賦值的屬性
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'shipping_fee',
        'total_amount',
        'shipping_name',
        'shipping_phone',
        'shipping_email',
        'shipping_postal_code',
        'shipping_city',
        'shipping_address',
        'shipping_method',
        'notes',
        'paid_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * 屬性轉換
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * 取得訂單所屬會員
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 取得訂單的所有明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * 取得訂單的付款記錄
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Scope: 查詢會員訂單
     */
    public function scopeForUser($query, int $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    /**
     * Scope: 依狀態篩選
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: 按建立時間倒序
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 取得狀態標籤
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * 檢查是否可取消（pending 直接取消，processing 需先退款）
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * 檢查管理員是否可取消（含 shipped 狀態）
     */
    public function canBeCancelledByAdmin(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
        ]);
    }

    /**
     * 檢查是否可發起付款
     */
    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 產生訂單編號
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -5));

        return "ORD-{$date}-{$random}";
    }
}
