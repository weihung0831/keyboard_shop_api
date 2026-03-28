<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentController extends Controller
{
    private PaymentService $payment_service;

    public function __construct(PaymentService $payment_service)
    {
        $this->payment_service = $payment_service;
    }

    /**
     * 發起付款
     * POST /api/v1/orders/{id}/pay
     */
    public function initiate(Request $request, int $id): JsonResponse
    {
        try {
            $user_id = $request->user()->id;
            $result = $this->payment_service->initiatePayment($id, $user_id);

            return response()->json([
                'message' => '付款已建立，請前往綠界完成付款',
                'data' => [
                    'payment' => new PaymentResource($result['payment']),
                    'payment_html' => $result['payment_html'],
                ],
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('發起付款失敗', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => '發起付款失敗，請稍後再試',
            ], 500);
        }
    }

    /**
     * 查詢訂單付款狀態
     * GET /api/v1/orders/{id}/payment
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user_id = $request->user()->id;
            $payment = $this->payment_service->getPaymentByOrder($id, $user_id);

            return response()->json([
                'message' => '取得付款資訊成功',
                'data' => new PaymentResource($payment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), '不存在') ? 404 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        } catch (\Exception $e) {
            Log::error('查詢付款資訊失敗', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => '查詢付款資訊失敗，請稍後再試',
            ], 500);
        }
    }

    /**
     * 使用者付款紀錄列表
     * GET /api/v1/payments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user_id = $request->user()->id;
            $per_page = min(max((int) $request->input('per_page', 10), 1), 50);
            $payments = $this->payment_service->getUserPayments($user_id, $per_page);

            return response()->json([
                'message' => '取得付款紀錄成功',
                'data' => PaymentResource::collection($payments),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'total' => $payments->total(),
                    'per_page' => $payments->perPage(),
                    'last_page' => $payments->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('取得付款紀錄失敗', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => '取得付款紀錄失敗，請稍後再試',
            ], 500);
        }
    }

    /**
     * ECPay 回調
     * POST /api/v1/payments/callback
     * 無需認證，以 CheckMacValue 驗證
     */
    public function callback(Request $request)
    {
        try {
            $this->payment_service->handleCallback($request->all());

            return response('1|OK', 200)
                ->header('Content-Type', 'text/plain');
        } catch (InvalidArgumentException $e) {
            return response('0|ErrorMessage', 400)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            Log::error('ECPay callback 處理異常', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            // 仍回 "1|OK" 避免 ECPay 重送
            return response('1|OK', 200)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * 申請退款
     * POST /api/v1/orders/{id}/refund
     */
    public function refund(RefundPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $user_id = $request->user()->id;
            $payment = $this->payment_service->refundPayment($id, $user_id);

            return response()->json([
                'message' => '退款成功',
                'data' => new PaymentResource($payment),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            Log::error('退款 API 失敗', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => '退款處理失敗，請稍後再試',
            ], 502);
        } catch (\Exception $e) {
            Log::error('退款處理異常', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => '退款處理失敗，請稍後再試',
            ], 500);
        }
    }
}
