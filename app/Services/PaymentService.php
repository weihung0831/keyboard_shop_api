<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentService
{
    private EcpayService $ecpay_service;

    private OrderService $order_service;

    private PaymentRepository $payment_repository;

    public function __construct(
        EcpayService $ecpay_service,
        OrderService $order_service,
        PaymentRepository $payment_repository
    ) {
        $this->ecpay_service = $ecpay_service;
        $this->order_service = $order_service;
        $this->payment_repository = $payment_repository;
    }

    /**
     * 發起付款
     *
     * @return array{payment: Payment, payment_html: string}
     */
    public function initiatePayment(int $order_id, int $user_id): array
    {
        $order = $this->order_service->getOrderDetail($order_id, $user_id);

        if (! $order->canBePaid()) {
            throw new InvalidArgumentException(
                $order->status === Order::STATUS_CANCELLED
                    ? '訂單已取消，無法付款'
                    : '訂單已付款或狀態不正確'
            );
        }

        // 已有 pending payment → 產生新的 MerchantTradeNo 重新發起（ECPay 不接受重複編號）
        if ($order->payment) {
            if ($order->payment->isPending()) {
                $order->payment->update([
                    'merchant_trade_no' => Payment::generateMerchantTradeNo($order->id),
                ]);
                $order->payment->refresh();
                $html = $this->ecpay_service->buildPaymentForm($order, $order->payment);

                return ['payment' => $order->payment, 'payment_html' => $html];
            }
            throw new InvalidArgumentException('訂單已有付款記錄');
        }

        return DB::transaction(function () use ($order) {
            $payment = $this->payment_repository->create([
                'order_id' => $order->id,
                'merchant_trade_no' => Payment::generateMerchantTradeNo($order->id),
                'payment_method' => Payment::METHOD_CREDIT,
                'amount' => $order->total_amount,
                'status' => Payment::STATUS_PENDING,
            ]);

            $html = $this->ecpay_service->buildPaymentForm($order, $payment);

            return ['payment' => $payment, 'payment_html' => $html];
        });
    }

    /**
     * 查詢訂單付款狀態
     */
    public function getPaymentByOrder(int $order_id, int $user_id): Payment
    {
        $order = $this->order_service->getOrderDetail($order_id, $user_id);
        $payment = $this->payment_repository->findByOrderId($order->id);

        if (! $payment) {
            throw new InvalidArgumentException('此訂單尚無付款記錄');
        }

        return $payment;
    }

    /**
     * 使用者付款紀錄列表
     */
    public function getUserPayments(int $user_id, int $per_page = 10): LengthAwarePaginator
    {
        return $this->payment_repository->getByUserId($user_id, $per_page);
    }

    /**
     * 處理 ECPay callback
     *
     * @param  array  $data  callback 原始資料
     * @return string 處理結果：verified/failed/handled/simulated/not_found
     *
     * @throws InvalidArgumentException CheckMacValue 驗證失敗
     */
    public function handleCallback(array $data): string
    {
        if (! $this->ecpay_service->verifyCallback($data)) {
            throw new InvalidArgumentException('CheckMacValue 驗證失敗');
        }

        $merchant_trade_no = $data['MerchantTradeNo'] ?? '';
        $payment = $this->payment_repository->findByMerchantTradeNo($merchant_trade_no);

        if (! $payment) {
            Log::warning('ECPay callback: MerchantTradeNo 找不到對應記錄', [
                'merchant_trade_no' => $merchant_trade_no,
            ]);

            return 'not_found';
        }

        // 冪等檢查
        if ($payment->isPaid() || $payment->isRefunded()) {
            return 'handled';
        }

        $rtn_code = $data['RtnCode'] ?? '0';

        // SimulatePaid 檢查
        if (($data['SimulatePaid'] ?? '0') === '1') {
            Log::info('ECPay callback: SimulatePaid 模擬付款，不更新訂單', [
                'merchant_trade_no' => $merchant_trade_no,
            ]);
            $payment->update(['raw_callback' => $data]);

            return 'simulated';
        }

        // 付款成功 — 使用 atomic update 防止並發 race condition
        if ($rtn_code === '1') {
            return DB::transaction(function () use ($payment, $data) {
                // Atomic: 只有 status=pending 時才更新，防止並發重複處理
                $updated = Payment::where('id', $payment->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->update([
                        'status' => Payment::STATUS_PAID,
                        'trade_no' => $data['TradeNo'] ?? null,
                        'paid_at' => now(),
                        'raw_callback' => json_encode($data),
                        'updated_at' => now(),
                    ]);

                if ($updated === 0) {
                    return 'handled'; // 已被其他並發請求處理
                }

                $this->order_service->markOrderAsPaid($payment->order_id);

                return 'verified';
            });
        }

        // 付款失敗
        Payment::where('id', $payment->id)
            ->where('status', Payment::STATUS_PENDING)
            ->update([
                'status' => Payment::STATUS_FAILED,
                'raw_callback' => json_encode($data),
                'updated_at' => now(),
            ]);

        return 'failed';
    }

    /**
     * 退款
     *
     * @return Payment 更新後的 payment
     *
     * @throws InvalidArgumentException 業務規則錯誤
     * @throws \RuntimeException ECPay API 失敗
     */
    public function refundPayment(int $order_id, int $user_id): Payment
    {
        $order = $this->order_service->getOrderDetail($order_id, $user_id);
        $payment = $this->payment_repository->findByOrderId($order->id);

        if (! $payment) {
            throw new InvalidArgumentException('此訂單尚無付款記錄');
        }

        if ($payment->isRefunded()) {
            throw new InvalidArgumentException('訂單已退款');
        }

        if (! $payment->isPaid()) {
            throw new InvalidArgumentException('訂單尚未付款，無法退款');
        }

        if (! $payment->trade_no) {
            throw new InvalidArgumentException('付款記錄缺少綠界交易編號，無法退款，請聯繫客服');
        }

        $refund_amount = intval($payment->amount);
        $result = $this->ecpay_service->requestRefund($payment, $refund_amount);
        $rtn_code = $result['RtnCode'] ?? '0';

        if ($rtn_code === '1') {
            $payment->update([
                'status' => Payment::STATUS_REFUNDED,
                'refund_amount' => $payment->amount,
                'refunded_at' => now(),
                'raw_refund_response' => $result,
            ]);

            return $payment->fresh();
        }

        // 退款失敗 — 記錄回應但不改狀態
        $payment->update(['raw_refund_response' => $result]);

        Log::error('ECPay 退款失敗', [
            'order_id' => $order_id,
            'merchant_trade_no' => $payment->merchant_trade_no,
            'result' => $result,
        ]);

        throw new \RuntimeException('退款失敗：'.($result['RtnMsg'] ?? '未知錯誤'));
    }
}
