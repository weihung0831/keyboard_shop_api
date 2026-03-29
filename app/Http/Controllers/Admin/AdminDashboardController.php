<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 管理後台儀表板控制器
 * 提供統計資料 API
 */
class AdminDashboardController extends Controller
{
    public function __construct(private AdminDashboardService $dashboard_service) {}

    /**
     * 取得儀表板統計資料
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => ['sometimes', 'string', 'in:today,7d,30d,all'],
            ]);

            $period = $request->input('period', '30d');

            $stats = $this->dashboard_service->getStats($period);

            return response()->json([
                'message' => '取得儀表板統計資料成功',
                'data' => $stats,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '請求參數錯誤',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得統計資料失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
