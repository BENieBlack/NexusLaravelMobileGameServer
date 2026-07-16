<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use NexusMaintenance\Services\MaintenanceService;
use NexusMaintenance\DTOs\MaintenanceDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NexusUtilities\ClockUtility;

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
        $sysMaintenance = $this->maintenanceService->getMaintenanceInfo();

        if ($sysMaintenance === null) {
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
            'maintenance_mode' => $sysMaintenance->isMaintenance,
            'start_at' => $sysMaintenance->startAt,
            'end_at' => $sysMaintenance->endAt,
            'title' => $sysMaintenance->title,
            'message' => $sysMaintenance->message,
            'is_under_maintenance' => $sysMaintenance->isCurrentlyUnderMaintenance(),
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

        $startAt = $validated['start_at'] ?? null;
        $endAt = $validated['end_at'] ?? null;

        $sysMaintenance = new DtoMaintenance(
            isMaintenance: true,
            startAt: $startAt,
            endAt: $endAt,
            title: $validated['title'] ?? null,
            message: $validated['message'] ?? null,
            updatedAt: ClockUtility::nowToString()
        );

        $this->maintenanceService->startMaintenance($sysMaintenance);

        return response()->json([
            'message' => 'Maintenance mode activated',
            'maintenance_mode' => true,
            'start_at' => $startAt,
            'end_at' => $endAt,
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
