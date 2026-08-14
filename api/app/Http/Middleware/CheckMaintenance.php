<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NexusMaintenance\Services\MaintenanceService;
use Symfony\Component\HttpFoundation\Response;

/**
 * メンテナンスチェックミドルウェア
 *
 * 全APIリクエストでメンテナンス状態をチェックし、
 * メンテナンス中の場合は503エラーを返す
 *
 * 除外設定:
 * - 設定ファイル（config/maintenance.php）でIPアドレスとルートを除外可能
 * - excluded_ips: メンテ中でもアクセス可能なIPアドレス
 * - excluded_routes: メンテ中でもアクセス可能なルート（ワイルドカード対応）
 */
class CheckMaintenance
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 除外IP判定（設定で指定されたIPはメンテ中でもアクセス可能）
        $excludedIps = config('maintenance.excluded_ips', []);
        $clientIp = $request->ip();

        if (in_array($clientIp, $excludedIps, true)) {
            return $next($request);
        }

        // 除外ルート判定（設定で指定されたルートはメンテ中でもアクセス可能）
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // メンテナンス状態チェック
        if (! $this->maintenanceService->isUnderMaintenance()) {
            return $next($request);
        }

        // メンテナンス中は503エラーを返す
        $sysMaintenance = $this->maintenanceService->findMaintenanceInfo();

        return response()->json([
            'error' => 'Service Unavailable',
            'message' => $sysMaintenance?->getMessage() ?? 'System is currently under maintenance',
            'title' => $sysMaintenance?->getTitle() ?? 'Maintenance',
            'start_at' => $sysMaintenance?->getStartAt(),
            'end_at' => $sysMaintenance?->getEndAt(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * リクエストが除外ルートに該当するかチェック
     */
    private function isExcludedRoute(Request $request): bool
    {
        $excludedRoutes = config('maintenance.excluded_routes', []);
        $currentPath = trim($request->path(), '/');

        foreach ($excludedRoutes as $pattern) {
            $pattern = trim($pattern, '/');

            // ワイルドカードパターンを正規表現に変換
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#';

            if (preg_match($regex, $currentPath)) {
                return true;
            }
        }

        return false;
    }
}
