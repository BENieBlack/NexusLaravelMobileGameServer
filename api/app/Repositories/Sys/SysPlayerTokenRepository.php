<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerToken;
use Nexus\Core\Support\CustomCollection;
use NexusAuth\Contracts\TokenRepositoryInterface;

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
     * refresh_token_hashから有効なトークンを取得（キャッシュを通さない）
     */
    public function selectValidByHash(string $tokenHash): ?SysPlayerToken
    {
        // 同じリクエストで発行したトークンは、まだDBに無い可能性がある。
        // キューに積んだ自分の行だけは先に見る
        $queued = $this->findCachedModels()->first(
            fn (SysPlayerToken $token) => $token->getRefreshTokenHash() === $tokenHash && $token->isValid()
        );

        if ($queued !== null) {
            /** @var SysPlayerToken */
            return $queued;
        }

        // トークンの照合は認証そのものなので、
        // この時点ではログイン中プレイヤーが確定していない。
        // 読むだけで、キャッシュにも更新キューにも載せない
        /** @var SysPlayerToken|null */
        return $this->selectWithoutCache()
            ->where('refresh_token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
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
        if ($this->isSessionPlayer($sysPlayerId)) {
            /** @var CustomCollection<int, SysPlayerToken> $tokens */
            $tokens = $this->queryOrMemory()
                ->filter(fn (SysPlayerToken $token) => $token->getSysPlayerId() === $sysPlayerId
                    && $token->isValid())
                ->values();

            return $tokens;
        }

        /** @var CustomCollection<int, SysPlayerToken> $tokens */
        $tokens = new CustomCollection(
            $this->selectWithoutCache()
                ->where('sys_player_id', $sysPlayerId)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->get()
                ->all()
        );

        return $tokens;
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

            // UPDATEをキューに積む
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

        // 各トークンを個別に物理削除キューへ積む
        foreach ($sysPlayerTokenCollection as $sysPlayerToken) {
            $this->hardDeleteModel($sysPlayerToken);
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

        $this->hardDeleteModel($sysPlayerToken);

        return 1;
    }
}
