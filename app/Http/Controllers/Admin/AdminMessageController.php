<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理員客服留言控制器
 * 處理留言列表、詳情與刪除
 */
class AdminMessageController extends Controller
{
    /**
     * 取得留言列表（支援已讀/未讀篩選，最新優先）
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => '權限不足'], 403);
        }

        try {
            $query = ContactMessage::query()->latest();

            // 依已讀狀態篩選
            if ($request->filled('is_read')) {
                $is_read = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($is_read !== null) {
                    $query->where('is_read', $is_read);
                }
            }

            $per_page = min((int) $request->input('per_page', 15), 50);
            $messages = $query->paginate($per_page);

            return response()->json([
                'message' => '取得留言列表成功',
                'data' => $messages->items(),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'last_page' => $messages->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得留言列表失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得單一留言詳情，自動標記為已讀
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => '權限不足'], 403);
        }

        try {
            $message = ContactMessage::findOrFail($id);

            // 若尚未讀取，自動標記為已讀
            if (! $message->is_read) {
                $message->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'message' => '取得留言詳情成功',
                'data' => $message->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => '留言不存在'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '取得留言詳情失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除留言（僅 super_admin）
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => '權限不足，只有超級管理員可以刪除留言'], 403);
        }

        try {
            $message = ContactMessage::findOrFail($id);
            $message->delete();

            return response()->json(['message' => '留言已刪除']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => '留言不存在'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '刪除留言失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
