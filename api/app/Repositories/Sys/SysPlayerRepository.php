<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Nexus\Core\Support\CustomCollection;
use NexusUnitOfWork\Contracts\QueryManagerInterface;

/**
 * SysPlayerRepository
 *
 * プレイヤー情報のRepository実装
 *
 * @extends _BaseSysRepository<SysPlayer>
 */
class SysPlayerRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayer::class;

    /**
     * プレイヤーを作成して即座にコミット（即コミット専用）
     *
     * SignUpなど、即座にIDが必要な場合に使用。
     * Repository内でinsertSysPlayer()を実行してIDを取得する。
     *
     * @return SysPlayer 作成されたプレイヤー（IDが設定済み）
     */
    public function insertPlayerAndCommit(): SysPlayer
    {
        $sysPlayer = new SysPlayer([
            'my_id' => Str::random(8),
            'uuid' => Str::uuid()->toString(),
            'name' => Str::random(8),
        ]);

        $this->setModel($sysPlayer);

        // INSERT処理のみを即座に実行してIDを取得
        app()->make(QueryManagerInterface::class)->flush();

        return $sysPlayer;
    }

    /**
     * IDでプレイヤーを検索
     * メモリキャッシュから取得、なければDBから取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function selectById(int $sysPlayerId): ?SysPlayer
    {
        // メモリキャッシュから取得
        $sysPlayer = $this->findCachedModel($sysPlayerId);

        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }

        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::find($sysPlayerId);

        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }

        return $sysPlayer;
    }

    /**
     * my_idからプレイヤーを検索
     * メモリキャッシュから検索、なければDBから取得
     */
    public function selectByMyId(string $myId): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->findCachedModels()->firstWhere('my_id', $myId);

        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }

        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::where('my_id', $myId)->first();

        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }

        return $sysPlayer;
    }

    /**
     * UUIDからプレイヤーを検索
     * メモリキャッシュから検索、なければDBから取得
     */
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->findCachedModels()->firstWhere('uuid', $uuid);

        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }

        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::where('uuid', $uuid)->first();

        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }

        return $sysPlayer;
    }

    /**
     * my_idが既に存在するかチェック
     */
    public function existsByMyId(string $myId): bool
    {
        // メモリキャッシュから検索
        if ($this->findCachedModels()->where('my_id', $myId)->isNotEmpty()) {
            return true;
        }

        // DBで確認
        return $this->modelClass::where('my_id', $myId)->exists();
    }

    /**
     * 最終ログイン日時を更新
     */
    public function updateLastLoginAt(SysPlayer $sysPlayer, CarbonImmutable $loginAt): void
    {
        $sysPlayer->last_login_at = $loginAt;
        $this->setModel($sysPlayer);
    }

    /**
     * VIPポイントの範囲でプレイヤーを取得
     *
     * @return CustomCollection<int, SysPlayer>
     */
    public function selectByVipPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): CustomCollection
    {
        $query = $this->modelClass::where('vip_point', '>=', $minPoint);

        if ($maxPoint !== null) {
            $query->where('vip_point', '<=', $maxPoint);
        }

        $models = $query->orderByDesc('vip_point')
            ->limit($limit)
            ->get();

        return new CustomCollection($models->all());
    }
}
