<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository
{
    public function findByOrderId(int $order_id): ?Payment
    {
        return Payment::where('order_id', $order_id)->first();
    }

    public function findByMerchantTradeNo(string $merchant_trade_no): ?Payment
    {
        return Payment::where('merchant_trade_no', $merchant_trade_no)->first();
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function getByUserId(int $user_id, int $per_page = 10): LengthAwarePaginator
    {
        return Payment::forUser($user_id)
            ->with('order')
            ->latest()
            ->paginate($per_page);
    }
}
