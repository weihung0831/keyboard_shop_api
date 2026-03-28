<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AutoSubmitFormService;
use Ecpay\Sdk\Services\CheckMacValueService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ECPay SDK 封裝
 * 僅負責與綠界 API 互動，不含業務邏輯
 */
class EcpayService
{
    private string $merchant_id;

    private string $hash_key;

    private string $hash_iv;

    private string $service_url;

    private string $return_url;

    private string $order_result_url;

    private string $refund_url;

    private Factory $factory;

    public function __construct()
    {
        $this->merchant_id = config('ecpay.merchant_id');
        $this->hash_key = config('ecpay.hash_key');
        $this->hash_iv = config('ecpay.hash_iv');
        $this->service_url = config('ecpay.service_url');
        $this->return_url = config('ecpay.return_url');
        $this->order_result_url = config('ecpay.order_result_url');
        $this->refund_url = config('ecpay.refund_url');

        $this->factory = new Factory([
            'hashKey' => $this->hash_key,
            'hashIV' => $this->hash_iv,
        ]);
    }

    /**
     * 組建付款參數並產生結帳表單 HTML
     *
     * @return string 自動提交的 HTML 表單
     */
    public function buildPaymentForm(Order $order, Payment $payment): string
    {
        $input = [
            'MerchantID' => $this->merchant_id,
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => intval($order->total_amount),
            'TradeDesc' => 'Keyboard Shop 鍵盤商城',
            'ItemName' => $this->buildItemName($order),
            'ReturnURL' => $this->return_url,
            'ChoosePayment' => 'Credit',
        ];

        if (! empty($this->order_result_url)) {
            $input['OrderResultURL'] = $this->order_result_url;
        }

        // 先算 CheckMacValue（不含 EncryptType），再加入 EncryptType 到表單
        /** @var CheckMacValueService $check_mac */
        $check_mac = $this->factory->create('Ecpay\Sdk\Services\CheckMacValueService');
        $input['CheckMacValue'] = $check_mac->generate($input);
        $input['EncryptType'] = 1;

        // 產生自動提交表單 HTML
        /** @var AutoSubmitFormService $auto_submit */
        $auto_submit = $this->factory->create('AutoSubmitFormService');

        return $auto_submit->generate($input, $this->service_url, '_self', 'ecpay-checkout', '前往付款');
    }

    /**
     * 驗證 callback 的 CheckMacValue
     *
     * @param  array  $data  callback 原始資料
     * @return bool 驗證是否通過
     */
    public function verifyCallback(array $data): bool
    {
        try {
            /** @var CheckMacValueService $check_mac */
            $check_mac = $this->factory->create('Ecpay\Sdk\Services\CheckMacValueService');

            return $check_mac->verify($data);
        } catch (\Exception $e) {
            Log::warning('ECPay callback 驗證失敗', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    /**
     * 呼叫退款 API（信用卡退款）
     *
     * @param  Payment  $payment  付款記錄（需有 trade_no）
     * @param  int  $amount  退款金額（整數）
     * @return array 綠界原始回應
     *
     * @throws \RuntimeException 退款失敗時
     */
    public function requestRefund(Payment $payment, int $amount): array
    {
        /** @var CheckMacValueService $check_mac */
        $check_mac = $this->factory->create('Ecpay\Sdk\Services\CheckMacValueService');

        $params = [
            'MerchantID' => $this->merchant_id,
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'TradeNo' => $payment->trade_no,
            'Action' => 'R',
            'TotalAmount' => $amount,
        ];

        $params['CheckMacValue'] = $check_mac->generate($params);

        try {
            $response = Http::asForm()->post($this->refund_url, $params);
            $result = $this->parseRefundResponse($response->body());

            Log::info('ECPay 退款回應', [
                'merchant_trade_no' => $payment->merchant_trade_no,
                'result' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('ECPay 退款失敗', [
                'error' => $e->getMessage(),
                'merchant_trade_no' => $payment->merchant_trade_no,
            ]);
            throw new \RuntimeException('綠界退款請求失敗：'.$e->getMessage());
        }
    }

    /**
     * 從訂單項目組建 ItemName（以 # 分隔）
     */
    private function buildItemName(Order $order): string
    {
        $order->loadMissing('items');

        $names = $order->items->map(function ($item) {
            return "{$item->product_name} x{$item->quantity}";
        })->toArray();

        return implode('#', $names);
    }

    /**
     * 解析退款回應（key=value& 格式）
     */
    private function parseRefundResponse(string $body): array
    {
        $result = [];
        parse_str($body, $result);

        if (empty($result)) {
            return ['RtnCode' => '0', 'RtnMsg' => $body];
        }

        return $result;
    }
}
