<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Ecpay\Sdk\Factories\Factory;
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
            'IgnorePayment' => 'WebATM#ATM#CVS#BARCODE#ApplePay#TWQR',
            'EncryptType' => 1,
        ];

        if (! empty($this->order_result_url)) {
            $input['OrderResultURL'] = $this->order_result_url;
        }

        $input['CheckMacValue'] = $this->generateCheckMacValue($input);

        // 產生自動提交表單 HTML
        $auto_submit = $this->factory->create('AutoSubmitFormService');

        return $auto_submit->generate($input, $this->service_url, '_self', 'ecpay-checkout', '前往付款');
    }

    /**
     * 驗證 callback 的 CheckMacValue
     */
    public function verifyCallback(array $data): bool
    {
        try {
            $received_mac = $data['CheckMacValue'] ?? '';
            $calculated_mac = $this->generateCheckMacValue($data);

            return strcasecmp($received_mac, $calculated_mac) === 0;
        } catch (\Exception $e) {
            Log::warning('ECPay callback 驗證失敗', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 呼叫退款 API（信用卡退款）
     *
     * @throws \RuntimeException 退款失敗時
     */
    public function requestRefund(Payment $payment, int $amount): array
    {
        $params = [
            'MerchantID' => $this->merchant_id,
            'MerchantTradeNo' => $payment->merchant_trade_no,
            'TradeNo' => $payment->trade_no,
            'Action' => 'R',
            'TotalAmount' => $amount,
        ];

        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

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
     * 產生 CheckMacValue（依照 ECPay 官方規範）
     * 排序 → 前後加 HashKey/HashIV → URL encode（.NET 相容）→ 小寫 → SHA256 → 大寫
     */
    private function generateCheckMacValue(array $params): string
    {
        unset($params['CheckMacValue']);

        uksort($params, 'strcasecmp');

        $str = 'HashKey='.$this->hash_key.'&';
        foreach ($params as $key => $value) {
            $str .= $key.'='.$value.'&';
        }
        $str .= 'HashIV='.$this->hash_iv;

        $str = urlencode($str);

        // .NET HttpUtility.UrlEncode 相容轉換
        $str = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29', '%20'],
            ['-', '_', '.', '!', '*', '(', ')', '+'],
            strtolower($str)
        );

        return strtoupper(hash('sha256', $str));
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
