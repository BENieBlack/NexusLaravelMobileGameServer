<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * メンテナンスチェックミドルウェア
 * 
 * 全APIリクエストでメンテナンス状態をチェックし、
 * メンテナンス中の場合は503エラーを返す
 */
class CheckMaintenance
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 除外IP判定（設定で指定されたIPはメンテ中でもアクセス可能）
        $excludedIps = config('maintenance.excluded_ips', []);
        $clientIp = $request->ip();
        
        if (in_array($clientIp, $excludedIps, true)) {
            return $next($request);
        }

        // メンテナンス状態チェック
        if (!$this->maintenanceService->isUnderMaintenance()) {
            return $next($request);
        }

        // メンテナンス中は503エラーを返す
        $maintenanceInfo = $this->maintenanceService->getMaintenanceInfo();
        
        return response()->json([
            'error' => 'Service Unavailable',
            'message' => $maintenanceInfo?->message ?? 'System is currently under maintenance',
            'title' => $maintenanceInfo?->title ?? 'Maintenance',
            'start_at' => $maintenanceInfo?->startAt?->toIso8601String(),
            'end_at' => $maintenanceInfo?->endAt?->toIso8601String(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
