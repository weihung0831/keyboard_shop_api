<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchUpdateSettingsRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Http\Resources\Admin\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理後台系統設定控制器
 * index 任何管理員皆可查看，update/batchUpdate 僅 super_admin 可操作
 */
class AdminSettingController extends Controller
{
    /**
     * 取得系統設定列表（可依 group 篩選）
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemSetting::query()->orderBy('group')->orderBy('key');

        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }

        $settings = $query->get();

        return response()->json([
            'message' => '取得系統設定列表成功',
            'data' => SystemSettingResource::collection($settings),
        ]);
    }

    /**
     * 更新單一系統設定（僅 super_admin）
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $setting = SystemSetting::where('key', $validated['key'])->firstOrFail();
        $setting->update(['value' => $validated['value']]);

        return response()->json([
            'message' => '系統設定已更新',
            'data' => new SystemSettingResource($setting->fresh()),
        ]);
    }

    /**
     * 批次更新系統設定（僅 super_admin）
     */
    public function batchUpdate(BatchUpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $updated = [];

        foreach ($validated['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->firstOrFail();
            $setting->update(['value' => $item['value']]);
            $updated[] = new SystemSettingResource($setting->fresh());
        }

        return response()->json([
            'message' => '系統設定批次更新成功',
            'data' => $updated,
        ]);
    }
}
