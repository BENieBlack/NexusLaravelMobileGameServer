# Nexus Auth

デバイスベース認証パッケージ - モバイルゲーム向けOAuth2風トークン管理

## 概要

nexus-authは、モバイルゲームで一般的なデバイスベース認証を提供する再利用可能なパッケージです。

### 主な機能

- デバイスUUIDベースのプレイヤー認証
- JWT風アクセストークン生成・検証
- リフレッシュトークンによるトークンローテーション
- プレイヤー・デバイス管理の抽象化
- Laravel/Eloquentから独立したインターフェース設計

## インストール

```bash
composer require nexus/auth
```

## 依存パッケージ

- `nexus/utilities` - ClockUtility等
- `nexus/security` - TokenValidatorInterface
- `nexus/unit-of-work` - QueryManagerInterface

## アーキテクチャ

### Contracts (インターフェース)

アプリケーション側でEloquentモデルとRepositoryに実装:

- `TokenModelInterface` - トークンモデル
- `TokenRepositoryInterface` - トークンリポジトリ

プレイヤーとデバイスの契約は nexus-core が持つ（このパッケージはそれに依存する）:

- `Nexus\Core\Contracts\PlayerModelInterface` - プレイヤーモデル
- `Nexus\Core\Contracts\DeviceModelInterface` - デバイスモデル
- `Nexus\Core\Repositories\PlayerRepositoryInterface` - プレイヤーリポジトリ
- `Nexus\Core\Repositories\PlayerDeviceRepositoryInterface` - デバイスリポジトリ

### Services

- `PlayerAuthService` - プレイヤー作成・管理
- `TokenService` - トークン生成・検証
- `TokenValidator` - NexusSecurityインターフェース実装

### DTOs

- `Token` - アクセストークン・リフレッシュトークンのDTO

## 使い方

### 1. モデルにインターフェースを実装

```php
use Nexus\Core\Contracts\PlayerModelInterface;

class SysPlayer extends Model implements PlayerModelInterface
{
    public function getId(): int { return $this->id; }
    public function getUuid(): string { return $this->uuid; }
    public function getCreatedAt(): string { return $this->created_at->format('Y-m-d H:i:s'); }
}
```

### 2. Repositoryにインターフェースを実装

```php
use Nexus\Core\Repositories\PlayerRepositoryInterface;

class PlayerRepositoryAdapter implements PlayerRepositoryInterface
{
    // 生成だけはDBの採番が要るためモデルを返す
    public function insertPlayerAndCommit(): PlayerModelInterface { /* ... */ }

    // 参照・更新はDTOでやりとりする
    public function selectById(int $id): ?Player { /* ... */ }
}
```

### 3. DIコンテナに登録

```php
// AppServiceProvider
use Nexus\Core\Repositories\PlayerRepositoryInterface;
use App\Repositories\Sys\PlayerRepositoryAdapter;

$this->app->bind(PlayerRepositoryInterface::class, PlayerRepositoryAdapter::class);
$this->app->bind(PlayerDeviceRepositoryInterface::class, SysPlayerDeviceRepository::class);
$this->app->bind(TokenRepositoryInterface::class, SysPlayerTokenRepository::class);

// TokenService登録
$this->app->singleton(TokenService::class, function ($app) {
    return new TokenService(
        tokenRepository: $app->make(TokenRepositoryInterface::class),
        appKey: config('app.key'),
        accessTokenExpiration: 3600, // 1時間
        refreshTokenExpirationDays: 30, // 30日
    );
});
```

### 4. サービスを使用

```php
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;

class SignUpUseCase
{
    public function __construct(
        private readonly PlayerAuthService $playerAuthService,
        private readonly TokenService $tokenService,
    ) {}

    public function handle(string $deviceId, array $deviceInfo)
    {
        // プレイヤー作成
        ['player' => $player, 'device' => $device] = $this->playerAuthService->createPlayer(
            deviceId: $deviceId,
            deviceInfo: $deviceInfo,
            deviceModelFactory: fn($playerId, $uuid, $info) => new SysPlayerDevice([
                'sys_player_id' => $playerId,
                'uuid' => $uuid,
                'device_info' => $info,
                'last_login_at' => now(),
            ])
        );

        // トークン生成
        [$token, $tokenModel] = $this->tokenService->generateToken(
            player: $player,
            device: $device,
            tokenModelFactory: fn($playerId, $deviceId, $hash, $expiresAt) => new SysPlayerToken([
                'sys_player_id' => $playerId,
                'sys_player_device_id' => $deviceId,
                'refresh_token_hash' => $hash,
                'expires_at' => $expiresAt,
            ])
        );

        return $token; // Token DTO
    }
}
```

## トークンフロー

### サインアップ

1. 新規デバイスID受信
2. プレイヤー作成
3. デバイス登録
4. アクセストークン + リフレッシュトークン発行

### サインイン

1. 既存デバイスID受信
2. デバイス検証
3. アクセストークン + リフレッシュトークン発行

### トークンリフレッシュ

1. リフレッシュトークン受信
2. リフレッシュトークン検証
3. 古いトークン無効化
4. 新しいアクセストークン + リフレッシュトークン発行

## セキュリティ考慮事項

### プロダクション推奨事項

1. **JWTライブラリ使用**: 本パッケージは簡易実装。プロダクションでは`tymon/jwt-auth`等を推奨
2. **HTTPS必須**: トークンは必ずHTTPS通信で送信
3. **リフレッシュトークンのハッシュ化**: SHA-256でハッシュ化してDB保存
4. **トークンローテーション**: リフレッシュ時に古いトークンを無効化
5. **有効期限管理**: アクセストークンは短期、リフレッシュトークンは長期

## ライセンス

MIT
