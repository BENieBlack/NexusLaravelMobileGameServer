<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Services\MaintenanceService;
use NexusMaintenance\ValueObjects\Maintenance;

/**
 * メンテナンス管理用の管理者APIコントローラー
 */
class MaintenanceController
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    /**
     * メンテナンス状態を取得
     */
    public function status(): JsonResponse
    {
        $sysMaintenance = $this->maintenanceService->findMaintenanceInfo();

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
            'maintenance_mode' => $sysMaintenance->getIsMaintenance(),
            'start_at' => $sysMaintenance->getStartAt(),
            'end_at' => $sysMaintenance->getEndAt(),
            'title' => $sysMaintenance->getTitle(),
            'message' => $sysMaintenance->getMessage(),
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

        $sysMaintenance = new Maintenance(
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
