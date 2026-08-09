<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerToken;
use NexusAuth\Contracts\TokenRepositoryInterface;
use Nexus\Core\Support\CustomCollection;

/**
 * SysPlayerTokenRepository
 *
 * プレイヤートークン情報のRepository実装
 *
 * @extends _BaseSysRepository<SysPlayerToken>
 */
class SysPlayerTokenRepository extends _BaseSysRepository implements TokenRepositoryInterface
{
    protected string $modelClass = SysPlayerToken::class;

    /**
     * refresh_tokenからトークンを取得（TokenRepositoryInterfaceの実装）
     * メモリキャッシュから検索、なければDBから取得
     */
    public function selectByRefreshToken(string $refreshToken): ?SysPlayerToken
    {
        return $this->selectValidByHash($refreshToken);
    }

    /**
     * refresh_token_hashから有効なトークンを取得
     * メモリキャッシュから検索、なければDBから取得
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
     * プレイヤーIDに紐づく有効なトークン一覧を取得
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @return CustomCollection<int, SysPlayerToken>
     */
    public function selectValidListByPlayerId(int $sysPlayerId): CustomCollection
    {
        // メモリキャッシュから検索
        $sysPlayerTokenCollection = $this->getModels()
            ->where('sys_player_id', $sysPlayerId)
            ->whereNull('revoked_at')
            ->filter(fn ($sysPlayerToken) => $sysPlayerToken->getExpiresAt() > now());

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
     * デバイスIDに紐づくトークンを無効化
     * メモリキャッシュも自動的に更新される
     *
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
     * プレイヤーIDに紐づくトークンを削除（TokenRepositoryInterfaceの実装）
     * メモリキャッシュも自動的に更新される
     *
     * @return int 削除したトークン数
     */
    public function deleteByPlayerId(int $playerId): int
    {
        // 対象のトークンを取得
        $sysPlayerTokenCollection = $this->modelClass::where('sys_player_id', $playerId)->get();

        $count = 0;

        // 各トークンを個別に削除
        foreach ($sysPlayerTokenCollection as $sysPlayerToken) {
            $sysPlayerToken->delete();
            $count++;
        }

        return $count;
    }

    /**
     * IDでトークンを削除（TokenRepositoryInterfaceの実装）
     * メモリキャッシュも自動的に更新される
     *
     * @return int 削除したトークン数
     */
    public function deleteById(int $tokenId): int
    {
        $sysPlayerToken = $this->modelClass::find($tokenId);

        if ($sysPlayerToken === null) {
            return 0;
        }

        $sysPlayerToken->delete();

        return 1;
    }
}
