<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\DtoToken;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * TokenService
 *
 * トークン生成と検証を担当するサービス
 * DB保存はPlayerServiceに委譲
 */
class TokenService
{
    /**
     * コンストラクタ
     *
     * @param SysPlayerTokenRepository $sysPlayerTokenRepository
     */
    public function __construct(
        private readonly SysPlayerTokenRepository $sysPlayerTokenRepository,
    ) {
    }
    /**
     * アクセストークンの有効期限（秒）
     * 1時間
     */
    private const ACCESS_TOKEN_EXPIRATION = 3600;

    /**
     * リフレッシュトークンの有効期限（日）
     * 30日
     */
    private const REFRESH_TOKEN_EXPIRATION_DAYS = 30;

    /**
     * アクセストークンを生成
     *
     * 注: 実際のプロダクションではJWTライブラリ（tymon/jwt-auth等）を使用
     * ここでは簡易的な実装
     *
     * @param SysPlayer $sysPlayer
     * @param SysPlayerDevice $sysPlayerDevice
     * @return string
     */
    public function generateAccessToken(SysPlayer $sysPlayer, SysPlayerDevice $sysPlayerDevice): string
    {
        // JWT標準のペイロード
        // 注: 'exp' = Expiration Time（有効期限）であり、経験値(experience)ではない
        $payload = [
            'player_id' => $sysPlayer->id,
            'uuid' => $sysPlayer->uuid,
            'device_id' => $sysPlayerDevice->id,
            'exp' => time() + self::ACCESS_TOKEN_EXPIRATION, // Expiration Time（有効期限）
            'iat' => time(), // Issued At（発行時刻）
        ];

        // 簡易的なJWT風トークン（実際にはJWTライブラリを使用すべき）
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payloadEncoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$payloadEncoded", config('app.key'), true);
        $signatureEncoded = base64_encode($signature);

        return "$header.$payloadEncoded.$signatureEncoded";
    }

    /**
     * リフレッシュトークンを検証
     *
     * @param string $refreshToken
     * @return SysPlayerToken|null
     */
    public function validateRefreshToken(string $refreshToken): ?SysPlayerToken
    {
        $tokenHash = hash('sha256', $refreshToken);
        return $this->sysPlayerTokenRepository->selectValidByHash($tokenHash);
    }

    /**
     * 古いトークンを無効化（同じデバイスの既存トークン）
     *
     * @param SysPlayerDevice $sysPlayerDevice
     * @return int 無効化したトークン数
     */
    public function revokeDeviceTokens(SysPlayerDevice $sysPlayerDevice): int
    {
        return $this->sysPlayerTokenRepository->revokeDeviceTokens($sysPlayerDevice->id);
    }

    /**
     * トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
     *
     * @param SysPlayerToken $oldToken
     * @return array{DtoToken, SysPlayerToken}
     */
    public function rotateToken(SysPlayerToken $oldToken): array
    {
        // 古いトークンを無効化
        $oldToken->revoke();

        // 新しいトークンを生成
        $sysPlayer = $oldToken->player;
        $sysPlayerDevice = $oldToken->device;

        // Token DTO生成
        [$dtoToken, $sysPlayerToken] = $this->generateToken($sysPlayer, $sysPlayerDevice);

        // 最終ログイン時刻を更新
        $sysPlayerDevice->updateLastLogin();

        return [$dtoToken, $sysPlayerToken];
    }

    /**
     * アクセストークンを検証
     *
     * @param string $token
     * @return array|null ペイロード（検証成功時）、null（失敗時）
     */
    public function validateAccessToken(string $token): ?array
    {
        try {
            // トークンを分解
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payloadEncoded, $signatureEncoded] = $parts;

            // 署名を検証
            $expectedSignature = hash_hmac('sha256', "$header.$payloadEncoded", config('app.key'), true);
            $expectedSignatureEncoded = base64_encode($expectedSignature);

            if ($signatureEncoded !== $expectedSignatureEncoded) {
                return null; // 署名が一致しない
            }

            // ペイロードをデコード
            $payload = json_decode(base64_decode($payloadEncoded), true);
            if (!$payload) {
                return null;
            }

            // 有効期限をチェック
            // 注: 'exp' = Expiration Time（有効期限）であり、経験値(experience)ではない
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                return null; // 期限切れ
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Token DTOを生成
     *
     * @param SysPlayer $sysPlayer
     * @param SysPlayerDevice $sysPlayerDevice
     * @return array{DtoToken, SysPlayerToken}
     */
    public function generateToken(SysPlayer $sysPlayer, SysPlayerDevice $sysPlayerDevice): array
    {
        // アクセストークン生成
        $accessToken = $this->generateAccessToken($sysPlayer, $sysPlayerDevice);

        // リフレッシュトークン生成
        $refreshToken = Str::random(64);
        $tokenHash = hash('sha256', $refreshToken);
        $expiresAt = CarbonImmutable::now()->addDays(self::REFRESH_TOKEN_EXPIRATION_DAYS);

        // Repository経由でトークンを作成して即座にコミット（IDを取得）
        $sysPlayerToken = $this->sysPlayerTokenRepository->createTokenAndCommit(
            $sysPlayer->id,
            $sysPlayerDevice->id,
            $tokenHash,
            $expiresAt
        );

        return [
            new DtoToken(
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresIn: self::ACCESS_TOKEN_EXPIRATION
            ),
            $sysPlayerToken,
        ];
    }
}
