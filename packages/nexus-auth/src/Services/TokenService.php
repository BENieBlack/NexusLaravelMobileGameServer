<?php

namespace NexusAuth\Services;

use NexusAuth\Contracts\DeviceModelInterface;
use NexusAuth\Contracts\PlayerModelInterface;
use NexusAuth\Contracts\TokenModelInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\DTOs\TokenDto;
use NexusUtilities\ClockUtility;
use Illuminate\Support\Str;

/**
 * TokenService
 *
 * トークン生成と検証を担当するサービス
 * OAuth2風のトークン管理を提供
 */
class TokenService
{
    /**
     * アクセストークンの有効期限（秒）
     * デフォルト: 1時間
     */
    private int $accessTokenExpiration;

    /**
     * リフレッシュトークンの有効期限（日）
     * デフォルト: 30日
     */
    private int $refreshTokenExpirationDays;

    /**
     * アプリケーションキー（署名用）
     */
    private string $appKey;

    /**
     * コンストラクタ
     *
     * @param TokenRepositoryInterface $tokenRepository
     * @param string $appKey アプリケーションキー（署名用）
     * @param int $accessTokenExpiration アクセストークン有効期限（秒）
     * @param int $refreshTokenExpirationDays リフレッシュトークン有効期限（日）
     */
    public function __construct(
        private readonly TokenRepositoryInterface $tokenRepository,
        string $appKey,
        int $accessTokenExpiration = 3600,
        int $refreshTokenExpirationDays = 30,
    ) {
        $this->appKey = $appKey;
        $this->accessTokenExpiration = $accessTokenExpiration;
        $this->refreshTokenExpirationDays = $refreshTokenExpirationDays;
    }

    /**
     * アクセストークンを生成
     *
     * 注: 実際のプロダクションではJWTライブラリ（tymon/jwt-auth等）を使用推奨
     * ここでは簡易的なJWT風実装を提供
     *
     * @param PlayerModelInterface $player
     * @param DeviceModelInterface $device
     * @return string
     */
    public function generateAccessToken(PlayerModelInterface $player, DeviceModelInterface $device): string
    {
        // JWT標準のペイロード
        $payload = [
            'player_id' => $player->getId(),
            'uuid' => $player->getUuid(),
            'device_id' => $device->getId(),
            'exp' => time() + $this->accessTokenExpiration, // Expiration Time
            'iat' => time(), // Issued At
        ];

        // 簡易的なJWT風トークン
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payloadEncoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$payloadEncoded", $this->appKey, true);
        $signatureEncoded = base64_encode($signature);

        return "$header.$payloadEncoded.$signatureEncoded";
    }

    /**
     * アクセストークンを検証
     *
     * @param string $token
     * @return array<string, mixed>|null ペイロード（検証成功時）、null（失敗時）
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
            $expectedSignature = hash_hmac('sha256', "$header.$payloadEncoded", $this->appKey, true);
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
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                return null; // 期限切れ
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Token DTOとTokenModelを生成
     *
     * @param PlayerModelInterface $player
     * @param DeviceModelInterface $device
     * @param callable $tokenModelFactory トークンモデルを生成するファクトリ関数
     * @return array{TokenDto, TokenModelInterface}
     */
    public function generateToken(
        PlayerModelInterface $player,
        DeviceModelInterface $device,
        callable $tokenModelFactory
    ): array {
        // アクセストークン生成
        $accessToken = $this->generateAccessToken($player, $device);

        // リフレッシュトークン生成
        $refreshToken = Str::random(64);
        $tokenHash = hash('sha256', $refreshToken);
        $expiresAt = ClockUtility::now()->addDays($this->refreshTokenExpirationDays);

        // トークンモデルを生成（アプリケーション側のファクトリに委譲）
        $tokenModel = $tokenModelFactory($player->getId(), $device->getId(), $tokenHash, $expiresAt->format('Y-m-d H:i:s'));

        // Repositoryに登録
        $this->tokenRepository->setModel($tokenModel);

        return [
            new TokenDto(
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresIn: $this->accessTokenExpiration
            ),
            $tokenModel,
        ];
    }

    /**
     * リフレッシュトークンを検証
     *
     * @param string $refreshToken
     * @return TokenModelInterface|null
     */
    public function validateRefreshToken(string $refreshToken): ?TokenModelInterface
    {
        $tokenHash = hash('sha256', $refreshToken);
        return $this->tokenRepository->selectByRefreshToken($tokenHash);
    }

    /**
     * トークンをローテーション（古いトークンを無効化して新しいトークンを発行）
     *
     * @param TokenModelInterface $oldToken
     * @param PlayerModelInterface $player
     * @param DeviceModelInterface $device
     * @param callable $tokenModelFactory
     * @return array{TokenDto, TokenModelInterface}
     */
    public function rotateToken(
        TokenModelInterface $oldToken,
        PlayerModelInterface $player,
        DeviceModelInterface $device,
        callable $tokenModelFactory
    ): array {
        // 古いトークンを無効化
        $oldToken->delete();

        // 新しいトークンを生成
        return $this->generateToken($player, $device, $tokenModelFactory);
    }

    /**
     * プレイヤーの全トークンを無効化
     *
     * @param int $playerId
     * @return int 無効化したトークン数
     */
    public function revokePlayerTokens(int $playerId): int
    {
        return $this->tokenRepository->deleteByPlayerId($playerId);
    }

    /**
     * アクセストークン有効期限を取得
     *
     * @return int 秒数
     */
    public function getAccessTokenExpiration(): int
    {
        return $this->accessTokenExpiration;
    }

    /**
     * リフレッシュトークン有効期限を取得
     *
     * @return int 日数
     */
    public function getRefreshTokenExpirationDays(): int
    {
        return $this->refreshTokenExpirationDays;
    }
}
