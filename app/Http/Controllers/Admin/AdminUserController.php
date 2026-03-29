<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理員會員控制器
 * 處理會員列表、詳情與角色更新
 */
class AdminUserController extends Controller
{
    /**
     * 取得會員列表（含搜尋與角色篩選）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query()->withCount('orders');

            // 搜尋姓名或 Email（跳脫 LIKE 萬用字元）
            if ($request->filled('search')) {
                $keyword = $this->escapeLike($request->input('search'));
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            }

            // 依角色篩選
            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }

            $query->latest();

            $per_page = min((int) $request->input('per_page', 15), 50);
            $users = $query->paginate($per_page);

            return response()->json([
                'message' => '取得會員列表成功',
                'data' => AdminUserResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得會員列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得會員詳情（含訂單統計與最近 10 筆訂單）
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = User::withCount('orders')->find($id);

            if (! $user) {
                return response()->json(['message' => '會員不存在'], 404);
            }

            // 訂單統計
            $order_stats = [
                'total_count' => $user->orders()->count(),
                'total_spent' => (float) $user->orders()
                    ->whereIn('status', [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED])
                    ->sum('total_amount'),
                'by_status' => [
                    Order::STATUS_PENDING => $user->orders()->where('status', Order::STATUS_PENDING)->count(),
                    Order::STATUS_PROCESSING => $user->orders()->where('status', Order::STATUS_PROCESSING)->count(),
                    Order::STATUS_SHIPPED => $user->orders()->where('status', Order::STATUS_SHIPPED)->count(),
                    Order::STATUS_COMPLETED => $user->orders()->where('status', Order::STATUS_COMPLETED)->count(),
                    Order::STATUS_CANCELLED => $user->orders()->where('status', Order::STATUS_CANCELLED)->count(),
                ],
            ];

            // 最近 10 筆訂單（摘要）
            $recent_orders = $user->orders()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'total_amount' => (float) $order->total_amount,
                    'created_at' => $order->created_at->toIso8601String(),
                ])
                ->values()
                ->all();

            return response()->json([
                'message' => '取得會員詳情成功',
                'data' => (new AdminUserDetailResource($user))->withOrderData($order_stats, $recent_orders),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得會員詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新會員角色
     * 只有 super_admin 可操作；不可修改自身；admin 不能設定 super_admin
     */
    public function updateRole(UpdateUserRoleRequest $request, int $id): JsonResponse
    {
        try {
            /** @var User $operator */
            $operator = $request->user();

            // authorize() 已確保 super_admin，但雙重防護以防 middleware 配置異常
            if (! $operator->isSuperAdmin()) {
                return response()->json(['message' => '權限不足，只有超級管理員可以變更角色'], 403);
            }

            // 不可修改自身角色
            if ($operator->id === $id) {
                return response()->json(['message' => '不可修改自身角色'], 422);
            }

            $target_user = User::find($id);

            if (! $target_user) {
                return response()->json(['message' => '會員不存在'], 404);
            }

            $new_role = $request->input('role');

            // admin 不能設定 super_admin（雖然 authorize() 已限制 super_admin 才能進來，此邏輯保留供未來擴充）
            if (! $operator->isSuperAdmin() && $new_role === User::ROLE_SUPER_ADMIN) {
                return response()->json(['message' => '權限不足，無法設定超級管理員角色'], 403);
            }

            // 直接賦值（role 不在 $fillable 中）
            $target_user->role = $new_role;
            $target_user->save();

            return response()->json([
                'message' => '會員角色已更新',
                'data' => [
                    'id' => $target_user->id,
                    'name' => $target_user->name,
                    'email' => $target_user->email,
                    'role' => $target_user->role,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '更新會員角色失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 跳脫 LIKE 查詢的萬用字元（% 和 _）
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
