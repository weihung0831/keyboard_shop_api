<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * 認證控制器
 * 處理會員註冊、登入、登出等 HTTP 請求
 */
class AuthController extends Controller
{
    /**
     * 認證服務
     */
    private AuthService $auth_service;

    /**
     * 建構函式
     */
    public function __construct(AuthService $auth_service)
    {
        $this->auth_service = $auth_service;
    }

    /**
     * 會員註冊
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // 執行註冊
            $result = $this->auth_service->register($request->validated());

            // 回傳成功回應（符合測試期望格式）
            return response()->json([
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_in' => 604800, // 7 天（秒）
                'user' => new UserResource($result['user']),
            ], 201);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '註冊失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 會員登入
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // 執行登入
            $result = $this->auth_service->login($request->validated());

            // 回傳成功回應（符合測試期望格式）
            return response()->json([
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_in' => 604800, // 7 天（秒）
                'user' => new UserResource($result['user']),
            ], 200);
        } catch (ValidationException $e) {
            // 回傳驗證錯誤
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 401);
        } catch (\Exception $e) {
            // 記錄錯誤詳情以便調試
            \Log::error('登入失敗: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // 回傳錯誤回應
            return response()->json([
                'message' => '登入失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 會員登出
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            // 刪除該用戶的所有 API tokens
            request()->user()->tokens()->delete();

            // 回傳成功回應
            return response()->json([
                'message' => '登出成功',
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '登出失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 忘記密碼 - 發送重設連結
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            // 發送密碼重設連結
            $message = $this->auth_service->sendPasswordResetLink($request->validated()['email']);

            // 回傳成功回應
            return response()->json([
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '發送密碼重設連結失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 重設密碼
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            // 執行密碼重設
            $this->auth_service->resetPassword($request->validated());

            // 回傳成功回應
            return response()->json([
                'message' => '密碼重設成功',
            ], 200);
        } catch (ValidationException $e) {
            // 回傳驗證錯誤
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json([
                'message' => '密碼重設失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
