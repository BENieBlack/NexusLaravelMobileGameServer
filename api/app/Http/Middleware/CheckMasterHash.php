<?php

namespace App\Http\Middleware;

use App\Repositories\Sys\SysDeployRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * クライアントのマスターハッシュを最新デプロイと比較する。
 * 不一致時もAPI処理は継続し、レスポンスヘッダーで更新要求を通知する。
 */
class CheckMasterHash
{
    public function __construct(
        private readonly SysDeployRepository $sysDeployRepository,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $clientHash = $request->header('X-Master-Hash');

        if ($clientHash === null || $response->getStatusCode() !== 200) {
            return $response;
        }

        try {
            $sysDeploy = $this->sysDeployRepository->selectLatestDownloadable();
            $masterHash = $sysDeploy?->deployMaster?->hash;

            if ($masterHash === null || hash_equals((string) $masterHash, $clientHash)) {
                return $response;
            }

            // 更新情報本体は /auth/version で取得するため、通常APIのBodyは変更しない。
            $response->headers->set('X-Master-Update-Required', 'true');
        } catch (Throwable) {
            // マスターハッシュ確認の失敗で本来のAPIレスポンスを壊さない。
        }

        return $response;
    }
}
