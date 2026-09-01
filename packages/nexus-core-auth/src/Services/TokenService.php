<?php

namespace NexusAuth\Services;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Str;
use Nexus\Core\Contracts\DeviceModelInterface;
use Nexus\Core\Contracts\PlayerModelInterface;
use Nexus\Core\Utilities\ClockUtility;
use NexusAuth\Contracts\TokenModelInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\ValueObjects\Token;

/**
 * TokenService
 *
 * トークン生成と検証を担当するサービス
 * Firebase JWT ライブラリを使用した安全なJWT実装
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
     * JWT Algorithm
     * HS256固定（セキュリティのため変更不可）
     */
    private const ALGORITHM = 'HS256';

    /**
     * コンストラクタ
     *
     * @param  TokenRepositoryInterface  $tokenRepository
     * @param  string  $appKey  アプリケーションキー（署名用、最低32文字推奨）
     * @param  int  $accessTokenExpiration  アクセストークン有効期限（秒）
     * @param  int  $refreshTokenExpirationDays  リフレッシュトークン有効期限（日）
     */
    public function __construct(
        private readonly TokenRepositoryInterface $tokenRepository,
        string $appKey,
        int $accessTokenExpiration = 3600,
        int $refreshTokenExpirationDays = 30,
    ) {
        if (strlen($appKey) < 32) {
            throw new \InvalidArgumentException('App key must be at least 32 characters long');
        }
        $this->appKey = $appKey;
        $this->accessTokenExpiration = $accessTokenExpiration;
        $this->refreshTokenExpirationDays = $refreshTokenExpirationDays;
    }

    /**
     * アクセストークンを生成
     *
     * firebase/php-jwt を使用した安全なJWT実装
     * - base64url エンコーディング
     * - タイミング攻撃耐性（hash_equals）
     * - alg 固定（HS256のみ）
     * - 鍵ローテーション対応（kid）
     *
     * @param  PlayerModelInterface  $player
     * @param  DeviceModelInterface  $device
     * @return string
     */
    public function generateAccessToken(PlayerModelInterface $player, DeviceModelInterface $device): string
    {
        $now = time();

        // JWT標準のペイロード
        $payload = [
            'iss' => config('app.url'),              // Issuer
            'sub' => (string) $player->getId(),       // Subject (player_id)
            'aud' => config('app.name'),             // Audience
            'exp' => $now + $this->accessTokenExpiration, // Expiration Time
            'iat' => $now,                           // Issued At
            'nbf' => $now,                           // Not Before
            'jti' => Str::uuid()->toString(),        // JWT ID (一意識別子)

            // カスタムクレーム
            'player_id' => $player->getId(),
            'uuid' => $player->getUuid(),
            'device_id' => $device->getId(),
        ];

        // kid（Key ID）をヘッダーに追加して鍵ローテーション対応
        return JWT::encode($payload, $this->appKey, self::ALGORITHM, $this->resolveCurrentKeyId());
    }

    /**
     * アクセストークンを検証
     *
     * @param  string  $token
     * @return array<string, mixed>|null ペイロード（検証成功時）、null（失敗時）
     */
    public function validateAccessToken(string $token): ?array
    {
        try {
            // JWT::decode は以下を自動的に検証:
            // - 署名の検証（hash_equals使用でタイミング攻撃耐性あり）
            // - exp（有効期限）
            // - nbf（Not Before）
            // - alg（アルゴリズム固定）
            $decoded = JWT::decode($token, new Key($this->appKey, self::ALGORITHM));

            // stdClass を配列に変換
            return json_decode(json_encode($decoded), true);

        } catch (ExpiredException $e) {
            // トークン期限切れ
            return null;
        } catch (SignatureInvalidException $e) {
            // 署名が不正
            return null;
        } catch (BeforeValidException $e) {
            // nbf より前
            return null;
        } catch (\Exception $e) {
            // その他のエラー（形式不正など）
            return null;
        }
    }

    /**
     * Token DTOとTokenModelを生成
     *
     * @param  PlayerModelInterface  $player
     * @param  DeviceModelInterface  $device
     * @param  callable  $tokenModelFactory  トークンモデルを生成するファクトリ関数
     * @return array{Token, TokenModelInterface}
     */
    public function generateToken(
        PlayerModelInterface $player,
        DeviceModelInterface $device,
        callable $tokenModelFactory
    ): array {
        // アクセストークン生成
        $accessToken = $this->generateAccessToken($player, $device);

        // リフレッシュトークン生成（暗号学的に安全な乱数）
        $refreshToken = bin2hex(random_bytes(32)); // 64文字の16進数文字列
        $tokenHash = hash('sha256', $refreshToken);
        $expiresAt = ClockUtility::now()->addDays($this->refreshTokenExpirationDays);

        // トークンモデルを生成（アプリケーション側のファクトリに委譲）
        $tokenModel = $tokenModelFactory($player->getId(), $device->getId(), $tokenHash, $expiresAt->format('Y-m-d H:i:s'));

        // Repositoryに登録
        $this->tokenRepository->setModel($tokenModel);

        return [
            new Token(
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
     * @param  string  $refreshToken
     * @return TokenModelInterface|null
     */
    public function validateRefreshToken(string $refreshToken): ?TokenModelInterface
    {
        $tokenHash = hash('sha256', $refreshToken);

        return $this->tokenRepository->selectByRefreshToken($tokenHash);
    }

    /**
     * トークンをローテーション（古いトークンを無効化し、新しいトークンを生成）
     *
     * @param  TokenModelInterface  $oldToken
     * @param  PlayerModelInterface  $player
     * @param  DeviceModelInterface  $device
     * @param  callable(int, int, string, string): TokenModelInterface  $tokenModelFactory
     * @return array{Token, TokenModelInterface}
     */
    public function rotateToken(
        TokenModelInterface $oldToken,
        PlayerModelInterface $player,
        DeviceModelInterface $device,
        callable $tokenModelFactory
    ): array {
        // 古いトークンを無効化
        $this->tokenRepository->deleteById($oldToken->getId());

        // 新しいトークンを生成
        return $this->generateToken($player, $device, $tokenModelFactory);
    }

    /**
     * プレイヤーの全トークンを無効化
     *
     * @param  int  $playerId
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

    /**
     * 現在の鍵IDを取得（将来の鍵ローテーション対応）
     *
     * @return string|null
     */
    private function resolveCurrentKeyId(): ?string
    {
        // 将来的に環境変数から取得できるようにする
        // 例: config('jwt.key_id') または環境変数 JWT_KEY_ID
        return config('jwt.key_id', null);
    }
}
