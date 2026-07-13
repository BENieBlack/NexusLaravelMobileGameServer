<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use LaravelMobileGame\Services\MaintenanceService;
use LaravelMobileGame\DTOs\MaintenanceInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * メンテナンス管理用の管理者APIコントローラー
 */
class MaintenanceController
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {
    }

    /**
     * メンテナンス状態を取得
     */
    public function status(): JsonResponse
    {
        $info = $this->maintenanceService->getMaintenanceInfo();

        if ($info === null) {
            return response()->json([
                'maintenance_mode' => false,
                'start_at' => null,
                'end_at' => null,
                'title' => null,
                'message' => null,
                'is_under_maintenance' => false,
            ]);
        }

        return response()->json([
            'maintenance_mode' => $info->isMaintenance,
            'start_at' => $info->startAt?->toIso8601String(),
            'end_at' => $info->endAt?->toIso8601String(),
            'title' => $info->title,
            'message' => $info->message,
            'is_under_maintenance' => $info->isCurrentlyUnderMaintenance(),
        ]);
    }

    /**
     * メンテナンスを開始
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'title' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:1000',
        ]);

        $startAt = isset($validated['start_at'])
            ? \Carbon\CarbonImmutable::parse($validated['start_at'])
            : null;
        $endAt = isset($validated['end_at'])
            ? \Carbon\CarbonImmutable::parse($validated['end_at'])
            : null;

        $info = new \LaravelMaintenance\DTOs\MaintenanceInfo(
            isMaintenance: true,
            startAt: $startAt,
            endAt: $endAt,
            title: $validated['title'] ?? null,
            message: $validated['message'] ?? null,
            updatedAt: \Carbon\CarbonImmutable::now()
        );

        $this->maintenanceService->startMaintenance($info);

        return response()->json([
            'message' => 'Maintenance mode activated',
            'maintenance_mode' => true,
            'start_at' => $startAt?->toIso8601String(),
            'end_at' => $endAt?->toIso8601String(),
        ]);
    }

    /**
     * メンテナンスを終了
     */
    public function end(): JsonResponse
    {
        $this->maintenanceService->endMaintenance();

        return response()->json([
            'message' => 'Maintenance mode deactivated',
            'maintenance_mode' => false,
        ]);
    }
}
