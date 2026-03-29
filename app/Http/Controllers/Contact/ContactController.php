<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

/**
 * 客服留言控制器（公開路由）
 */
class ContactController extends Controller
{
    /**
     * 建立客服留言
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        try {
            ContactMessage::create($request->validated());

            return response()->json([
                'message' => '感謝您的留言，我們會盡快回覆',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '留言送出失敗，請稍後再試',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
