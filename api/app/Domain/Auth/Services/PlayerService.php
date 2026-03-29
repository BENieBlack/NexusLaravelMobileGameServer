<?php

namespace App\Domain\Auth\Services;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * PlayerService
 *
 * プレイヤー作成と管理を担当するサービス
 */
class PlayerService
{
    /**
     * コンストラクタ
     *
     * @param SysPlayerRepository $sysPlayerRepository
     * @param SysPlayerDeviceRepository $sysPlayerDeviceRepository
     * @param SysPlayerTokenRepository $sysPlayerTokenRepository
     */
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
        private readonly SysPlayerTokenRepository $sysPlayerTokenRepository
    ) {
    }

    /**
     * 新しいプレイヤーを作成
     * 
     * 重要：この関数は、プレイヤーとデバイスをQuerySysManagerのキューに追加した後、
     * 即座にINSERTを実行してIDを取得します。
     * これにより、後続の処理（トークン生成など）でIDを使用できます。
     *
     * @param string $deviceId
     * @param array<string, mixed>|null $deviceInfo
     * @return array{SysPlayer, SysPlayerDevice}
     * @throws RandomException
     */
    public function createPlayer(string $deviceId, ?array $deviceInfo = null): array
    {
        // UUIDを生成（UUIDv4） - プレイヤー用
        $uuid = Str::uuid()->toString();

        // my_idを生成（8桁英数、紛らわしい文字除外）
        $myId = $this->generateMyId();

        // プレイヤーを作成（Unit of Work パターン）
        $sysPlayer = new SysPlayer([
            'uuid' => $uuid,
            'my_id' => $myId,
            'name' => $myId, // デフォルトではmy_idをnameに設定
        ]);
        $sysPlayer->exists = false; // INSERT として認識
        $this->sysPlayerRepository->setModel($sysPlayer);

        // **重要**: SysPlayerをINSERTしてIDを取得
        // これにより、$sysPlayer->idに値が設定される
        $querySysManager = app()->make(\App\Repositories\QueryManager::class);
        $querySysManager->execSysQuery(); // Sysのみを実行

        // デバイス情報を作成（Unit of Work パターン）
        // この時点で$sysPlayer->idは既に取得済み
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => $deviceId, // デバイスのUUID
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);
        $sysPlayerDevice->exists = false; // INSERT として認識
        $this->sysPlayerDeviceRepository->setModel($sysPlayerDevice);

        // **重要**: SysPlayerDeviceもINSERTしてIDを取得
        // sys_player_tokenでsys_player_device_idが必要なため
        $querySysManager->execSysQuery(); // Sysのみを実行

        return [$sysPlayer, $sysPlayerDevice];
    }

    /**
     * 既存デバイスからプレイヤーとデバイス情報を取得
     *
     * @param string $deviceId
     * @return array{SysPlayer, SysPlayerDevice}|null
     */
    public function findByDeviceId(string $deviceId): ?array
    {
        $sysPlayerDevice = $this->sysPlayerDeviceRepository->selectByDeviceId($deviceId);
        
        if ($sysPlayerDevice === null) {
            return null;
        }

        return [$sysPlayerDevice->player, $sysPlayerDevice];
    }

    /**
     * my_idを生成
     *
     * 8桁の英数大小文字（紛らわしい文字を除外: I, l, O, 0）
     * 重複チェック付き
     *
     * @return string
     * @throws RandomException
     */
    private function generateMyId(): string
    {
        // 紛らわしい文字を除外: I(大文字i), l(小文字L), O(大文字o), 0(ゼロ)
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz123456789';
        $charsetLength = strlen($charset);
        $length = 8;
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $myId = '';
            for ($i = 0; $i < $length; $i++) {
                $myId .= $charset[random_int(0, $charsetLength - 1)];
            }

            // 衝突チェック（Repositoryを使用）
            $exists = $this->sysPlayerRepository->existsByMyId($myId);

            if (!$exists) {
                return $myId;
            }
        }

        throw new \RuntimeException('Failed to generate unique my_id after ' . $maxAttempts . ' attempts');
    }

    /**
     * デバイスの最終ログイン日時を更新
     *
     * @param SysPlayerDevice $sysPlayerDevice
     * @return bool
     */
    public function updateLastLogin(SysPlayerDevice $sysPlayerDevice): bool
    {
        return $sysPlayerDevice->updateLastLogin();
    }

    /**
     * プレイヤートークンを作成
     *
     * リフレッシュトークンのハッシュと有効期限をDBに保存（Repository経由）
     * トークンの生成自体はTokenServiceが担当
     * 
     * トークンをQuerySysManagerのキューに追加します。
     * 実際のINSERTは、UseCaseTraitの最後でまとめて実行されます。
     *
     * @param SysPlayer $sysPlayer
     * @param SysPlayerDevice $sysPlayerDevice
     * @param string $refreshTokenHash リフレッシュトークンのSHA-256ハッシュ
     * @param int $expirationDays 有効期限（日数）
     * @return SysPlayerToken
     */
    public function createSysPlayerToken(
        SysPlayer $sysPlayer,
        SysPlayerDevice $sysPlayerDevice,
        string $refreshTokenHash,
        int $expirationDays
    ): SysPlayerToken {
        $expiresAt = CarbonImmutable::now()->addDays($expirationDays);

        // トークンを作成（Unit of Work パターン）
        $sysPlayerToken = new SysPlayerToken([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => $refreshTokenHash,
            'expires_at' => $expiresAt,
        ]);
        $sysPlayerToken->exists = false; // INSERT として認識
        $this->sysPlayerTokenRepository->setModel($sysPlayerToken);

        // SysPlayerTokenは後でまとめて実行される（UseCaseTraitの最後）

        return $sysPlayerToken;
    }
}
