<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCaseInterface;
use App\Domain\Auth\Services\TokenService;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Traits\UseCaseTrait;
use App\Traits\RequiresRefreshToken;

/**
 * RefreshTokenUseCase
 *
 * トークンリフレッシュのユースケース
 * リフレッシュトークンからアクセストークンを再発行
 * is_delete=trueのレコードを物理削除
 */
class RefreshTokenUseCase implements _BaseUseCaseInterface
{
    use UseCaseTrait;
    use RequiresRefreshToken;

    public function __construct(
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * トークンリフレッシュ処理を実行
     *
     * @param string $refreshToken リフレッシュトークン
     * @return RefreshTokenResponse
     * @throws \Exception|\Throwable
     */
    public function handle(string $refreshToken): RefreshTokenResponse
    {
        // リフレッシュトークンを検証（無効な場合は例外をスロー）
        $sysPlayerToken = $this->validateRefreshTokenOrFail($this->tokenService, $refreshToken);

        // トランザクション開始（クリーンアップ処理も含む）
        return $this->executeWithTransaction(function () use ($sysPlayerToken) {
            // トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
            [$dtoToken, ] = $this->tokenService->rotateToken($sysPlayerToken);

            return new RefreshTokenResponse(
                dtoToken: $dtoToken,
            );
        }, $sysPlayerToken->getSysPlayerId());
    }
}
