<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerToken;
use App\Repositories\QueryManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * SysPlayerTokenRepository
 * 
 * プレイヤートークン情報のRepository実装
 */
class SysPlayerTokenRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayerToken::class;

    /**
     * refresh_token_hashから有効なトークンを取得
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param string $tokenHash
     * @return SysPlayerToken|null
     */
    public function selectValidByHash(string $tokenHash): ?SysPlayerToken
    {
        // メモリキャッシュから検索
        $sysPlayerToken = $this->getModels()
            ->where('refresh_token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        
        if ($sysPlayerToken !== null) {
            /** @var SysPlayerToken */
            return $sysPlayerToken;
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayerToken = $this->modelClass::where('refresh_token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        
        if ($sysPlayerToken !== null) {
            $this->setModel($sysPlayerToken);
        }
        
        return $sysPlayerToken;
    }

    /**
     * デバイスIDに紐づくトークンを無効化
     * メモリキャッシュも自動的に更新される
     *
     * @param int $deviceId
     * @return int 無効化したトークン数
     */
    public function revokeDeviceTokens(int $deviceId): int
    {
        // 対象のトークンを取得
        $sysPlayerTokenCollection = $this->modelClass::where('sys_player_device_id', $deviceId)
            ->whereNull('revoked_at')
            ->get();
        
        $count = 0;
        
        // 各トークンを個別に更新してメモリキャッシュを更新
        foreach ($sysPlayerTokenCollection as $sysPlayerToken) {
            $sysPlayerToken->setRevokedAt(now());
            $sysPlayerToken->save();
            
            // メモリキャッシュを更新
            $this->setModel($sysPlayerToken);
            
            $count++;
        }
        
        return $count;
    }

    /**
     * プレイヤーIDに紐づく有効なトークン一覧を取得
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @return Collection<int, SysPlayerToken>
     */
    public function selectValidListByPlayerId(int $sysPlayerId): Collection
    {
        // メモリキャッシュから検索
        $sysPlayerTokenCollection = $this->getModels()
            ->where('sys_player_id', $sysPlayerId)
            ->whereNull('revoked_at')
            ->filter(fn($sysPlayerToken) => $sysPlayerToken->getExpiresAt() > now());
        
        if ($sysPlayerTokenCollection->isNotEmpty()) {
            return $sysPlayerTokenCollection->values();
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayerTokenCollection = $this->modelClass::where('sys_player_id', $sysPlayerId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get();
        
        foreach ($sysPlayerTokenCollection as $sysPlayerToken) {
            $this->setModel($sysPlayerToken);
        }
        
        return $sysPlayerTokenCollection;
    }

    /**
     * トークンを作成（遅延コミット版）
     * UPDATE時など、トランザクション終了時にコミットする場合に使用
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param int $sysPlayerDeviceId sys_player_device.id（デバイスID）
     * @param string $refreshTokenHash リフレッシュトークンのハッシュ値
     * @param CarbonImmutable $expiresAt 有効期限
     * @return SysPlayerToken 作成されたトークン（IDは未設定、トランザクション終了時に設定される）
     */
    public function createToken(
        int $sysPlayerId,
        int $sysPlayerDeviceId,
        string $refreshTokenHash,
        CarbonImmutable $expiresAt
    ): SysPlayerToken {
        $sysPlayerToken = new SysPlayerToken([
            'sys_player_id' => $sysPlayerId,
            'sys_player_device_id' => $sysPlayerDeviceId,
            'refresh_token_hash' => $refreshTokenHash,
            'expires_at' => $expiresAt,
        ]);
        
        $this->setModel($sysPlayerToken);
        
        return $sysPlayerToken;
    }

    /**
     * トークンを作成して即座にコミット（即コミット版）
     * SignUpなど、即座にIDが必要な場合に使用
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param int $sysPlayerDeviceId sys_player_device.id（デバイスID）
     * @param string $refreshTokenHash リフレッシュトークンのハッシュ値
     * @param CarbonImmutable $expiresAt 有効期限
     * @return SysPlayerToken 作成されたトークン（IDが設定済み）
     */
    public function createTokenAndCommit(
        int $sysPlayerId,
        int $sysPlayerDeviceId,
        string $refreshTokenHash,
        CarbonImmutable $expiresAt
    ): SysPlayerToken {
        $sysPlayerToken = new SysPlayerToken([
            'sys_player_id' => $sysPlayerId,
            'sys_player_device_id' => $sysPlayerDeviceId,
            'refresh_token_hash' => $refreshTokenHash,
            'expires_at' => $expiresAt,
        ]);
        
        $this->setModel($sysPlayerToken);
        
        // Repository内でexecSysQuery()を実行してIDを取得
        app()->make(QueryManager::class)->execSysQuery();
        
        return $sysPlayerToken;
    }
}
