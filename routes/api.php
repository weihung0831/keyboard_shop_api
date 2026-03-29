<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductSpecController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

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

    /**
     * 購物車相關（支援會員和訪客）
     * 會員使用 Bearer Token 認證
     * 訪客使用 X-Session-Id Header
     */
    Route::prefix('cart')->group(function () {
        // 取得購物車
        Route::get('/', [CartController::class, 'index'])
            ->name('api.cart.index');

        // 加入購物車
        Route::post('/items', [CartController::class, 'addItem'])
            ->name('api.cart.add-item');

        // 更新購物車項目數量
        Route::put('/items/{id}', [CartController::class, 'updateItem'])
            ->name('api.cart.update-item');

        // 移除購物車項目
        Route::delete('/items/{id}', [CartController::class, 'removeItem'])
            ->name('api.cart.remove-item');

        // 清空購物車
        Route::delete('/', [CartController::class, 'clear'])
            ->name('api.cart.clear');
    });

    /**
     * 購物車合併（需要認證）
     */
    Route::middleware('auth:sanctum')->group(function () {
        // 合併購物車（登入後呼叫）
        Route::post('/cart/merge', [CartController::class, 'merge'])
            ->name('api.cart.merge');

        /**
         * 訂單相關（需要認證）
         */
        Route::prefix('orders')->group(function () {
            // 取得訂單列表
            Route::get('/', [OrderController::class, 'index'])
                ->name('api.orders.index');

            // 取得訂單統計
            Route::get('/stats', [OrderController::class, 'stats'])
                ->name('api.orders.stats');

            // 建立訂單
            Route::post('/', [OrderController::class, 'store'])
                ->name('api.orders.store');

            // 取得訂單詳情
            Route::get('/{id}', [OrderController::class, 'show'])
                ->name('api.orders.show');

            // 取消訂單
            Route::put('/{id}/cancel', [OrderController::class, 'cancel'])
                ->name('api.orders.cancel');

            // 發起付款
            Route::post('/{id}/pay', [PaymentController::class, 'initiate'])
                ->name('api.orders.pay');

            // 查詢訂單付款狀態
            Route::get('/{id}/payment', [PaymentController::class, 'show'])
                ->name('api.orders.payment');

            // 申請退款
            Route::post('/{id}/refund', [PaymentController::class, 'refund'])
                ->name('api.orders.refund');
        });

        // 使用者付款紀錄列表
        Route::get('/payments', [PaymentController::class, 'index'])
            ->name('api.payments.index');
    });

    /**
     * ECPay 金流回調（無需認證，以 CheckMacValue 驗證）
     */
    Route::post('/payments/callback', [PaymentController::class, 'callback'])
        ->name('api.payments.callback');

    /**
     * 管理員 API（需要認證，isAdmin middleware 在各 FormRequest 的 authorize() 驗證）
     */
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

        /**
         * 訂單管理
         */
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('/{id}', [AdminOrderController::class, 'show']);
            Route::patch('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        });

        /**
         * 會員管理
         */
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('/{id}', [AdminUserController::class, 'show']);
            Route::patch('/{id}/role', [AdminUserController::class, 'updateRole']);
        });

        /**
         * 分類管理
         */
        Route::prefix('categories')->group(function () {
            Route::get('/', [AdminCategoryController::class, 'index']);
            Route::post('/', [AdminCategoryController::class, 'store']);
            Route::put('/{id}', [AdminCategoryController::class, 'update']);
            Route::delete('/{id}', [AdminCategoryController::class, 'destroy']);
        });

        /**
         * 儀表板統計
         */
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [AdminDashboardController::class, 'stats']);
        });

        /**
         * 系統設定（index 任何管理員可查，update/batchUpdate 僅 super_admin）
         */
        Route::prefix('settings')->group(function () {
            Route::get('/', [AdminSettingController::class, 'index']);
            Route::put('/', [AdminSettingController::class, 'update']);
            Route::put('/batch', [AdminSettingController::class, 'batchUpdate']);
        });

        /**
         * 商品管理
         * 靜態路由須在 {id} 萬用路由之前定義，避免路由衝突
         */
        Route::prefix('products')->middleware('throttle:60,1')->group(function () {
            // 批次切換上下架（靜態路由優先）
            Route::post('/batch-toggle', [AdminProductController::class, 'batchToggle']);

            // 低庫存商品列表（靜態路由優先）
            Route::get('/low-stock', [AdminProductController::class, 'lowStock']);

            // 商品 CRUD
            Route::get('/', [AdminProductController::class, 'index']);
            Route::post('/', [AdminProductController::class, 'store']);
            Route::get('/{id}', [AdminProductController::class, 'show']);
            Route::put('/{id}', [AdminProductController::class, 'update']);
            Route::delete('/{id}', [AdminProductController::class, 'destroy']);

            // 商品圖片管理
            Route::post('/{id}/images', [AdminProductController::class, 'uploadImages']);
            Route::delete('/{id}/images/{imageId}', [AdminProductController::class, 'deleteImage']);

            // 商品規格管理
            Route::get('/{id}/specs', [AdminProductSpecController::class, 'index']);
            Route::post('/{id}/specs', [AdminProductSpecController::class, 'store']);
            Route::put('/{id}/specs/{specId}', [AdminProductSpecController::class, 'update']);
            Route::delete('/{id}/specs/{specId}', [AdminProductSpecController::class, 'destroy']);
        });
    });
});
