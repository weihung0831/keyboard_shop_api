<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Category\CategoryController;

/**
 * API Version 1 路由定義
 */
Route::prefix('v1')->group(function () {

    /**
     * 認證相關路由（無需驗證）
     */
    Route::prefix('auth')->group(function () {
        // 會員註冊
        Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');

        // 會員登入（限制：1分鐘內最多5次）
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('api.auth.login');

        // 忘記密碼 - 發送重設連結
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');

        // 重設密碼
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');
    });

    /**
     * 需要認證的路由
     */
    Route::middleware('auth:sanctum')->group(function () {

        /**
         * 認證相關（需登入）
         */
        Route::prefix('auth')->group(function () {
            // 登出
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        });

        /**
         * 會員個人資料相關
         */
        Route::prefix('user')->group(function () {
            // 取得個人資料
            Route::get('/profile', [UserController::class, 'profile'])->name('api.user.profile');

            // 更新個人資料
            Route::put('/profile', [UserController::class, 'updateProfile'])->name('api.user.update-profile');

            // 修改密碼
            Route::put('/change-password', [UserController::class, 'updatePassword'])->name('api.user.update-password');
        });
    });

    /**
     * 公開路由（無需認證）
     */

    /**
     * 產品分類相關
     */
    Route::prefix('categories')->group(function () {
        // 取得所有分類列表
        Route::get('/', [CategoryController::class, 'index'])->name('api.categories.index');

        // 取得單一分類詳情（by ID 或 slug）
        Route::get('/{idOrSlug}', [CategoryController::class, 'show'])->name('api.categories.show');
    });

    /**
     * 產品相關
     */
    Route::prefix('products')->group(function () {
        // 取得產品列表（支援分頁、搜尋、篩選）
        Route::get('/', [ProductController::class, 'index'])->name('api.products.index');

        // 搜尋建議（需在 {idOrSlug} 之前定義，避免被視為 slug）
        Route::get('/search/suggestions', [ProductController::class, 'searchSuggestions'])->name('api.products.search-suggestions');

        // 取得單一產品詳情（by ID 或 slug）
        Route::get('/{idOrSlug}', [ProductController::class, 'show'])->name('api.products.show');
    });
});
