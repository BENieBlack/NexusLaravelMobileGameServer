<?php

namespace App\Http\Middleware;

use App\Constants\Platform;
use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use Closure;
use Illuminate\Http\Request;
use Nexus\Core\ValueObjects\ErrorResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolvePlatform
 *
 * X-Platform ヘッダーを検証し、ApiSession に載せる。
 *
 * - 許可値は Apple / Google（完全一致）。欠落・空・不明な値は HTTP 299 + 99003
 * - 順序は maintenance → platform → 署名・認証。メンテ中は 503 を先に返し、
 *   署名や認証より前に載せるので、未認証の sign_up でも ApiSession::getPlatform() を読める
 * - 付けるのは routes/api.php の maintenance グループだけ。version(GET)・メンテ状態・
 *   admin・broadcasting・/up はグループの外なので不要
 *
 * GameException は投げず、応答を直接返す。投げると bootstrap/app.php の handler が
 * Slack へ通知するため、クライアントの付け忘れ（実装の誤り）で通知が溢れる。
 * maskForProduction も付けない。欠落と不正値の違いを、本番でも実装者が読めるようにするため
 * （利用者の入力ではなく、メッセージに内部情報も含まれない）。
 *
 * platform は認可・課金検証などセキュリティの判断に使わない（署名の対象外）。
 */
class ResolvePlatform
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = $request->header('X-Platform');

        // ヘッダーが無い、または空文字
        if ($platform === null || $platform === '') {
            return ErrorResponse::businessError(
                errorCode: GameErrorCode::INVALID_PLATFORM,
                message: 'X-Platform header is required.',
            )->toJsonResponse();
        }

        // 許可値以外（小文字・iOS・Windows など）
        if (! Platform::isValid($platform)) {
            return ErrorResponse::businessError(
                errorCode: GameErrorCode::INVALID_PLATFORM,
                message: 'X-Platform must be one of: '.implode(', ', Platform::values()).'.',
            )->toJsonResponse();
        }

        ApiSession::setPlatform($platform);

        return $next($request);
    }
}
