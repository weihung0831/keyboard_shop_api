<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * 使用者控制器
 * 處理使用者個人資料相關 HTTP 請求
 */
class UserController extends Controller
{
    /**
     * 使用者服務
     */
    private UserService $user_service;

    /**
     * 建構函式
     */
    public function __construct(UserService $user_service)
    {
        $this->user_service = $user_service;
    }

    /**
     * 取得個人資料
     *
     * @return JsonResponse
     */
    public function profile(): JsonResponse
    {
        // 回傳當前使用者資料（使用 request()->user() 以正確取得 token）
        return response()->json([
            'message' => '取得個人資料成功',
            'data' => new UserResource(request()->user()),
        ], 200);
    }

    /**
     * 更新個人資料
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            // 執行更新（使用 request()->user() 以正確取得 token）
            $user = $this->user_service->updateProfile(
                $request->user(),
                $request->validated()
            );

            // 回傳成功回應
            return response()->json([
                'message' => '更新個人資料成功',
                'data' => new UserResource($user),
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '更新個人資料失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新密碼
     *
     * @param UpdatePasswordRequest $request
     * @return JsonResponse
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            // 執行更新（使用 request()->user() 以正確取得 token）
            $user = $this->user_service->updatePassword(
                $request->user(),
                $request->input('current_password'),
                $request->input('new_password')
            );

            // 回傳成功回應
            return response()->json([
                'message' => '密碼修改成功',
                'data' => new UserResource($user),
            ], 200);
        } catch (ValidationException $e) {
            // 回傳驗證錯誤
            return response()->json([
                'message' => '密碼修改失敗',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '密碼修改失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
