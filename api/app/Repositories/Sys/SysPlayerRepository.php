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

    /** 自分の行は主キーで決まる */
    protected array $selfScopeKeys = ['id'];

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
     *
     * 自分の行だけがキャッシュと更新キューに載る。
     * 他人のIDを渡した場合はキャッシュを通さず読むだけで、
     * 返ったモデルを setModel() に渡してはいけない。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function selectById(int $sysPlayerId): ?SysPlayer
    {
        if (! $this->isSessionPlayer($sysPlayerId)) {
            /** @var SysPlayer|null */
            return $this->selectWithoutCache()->find($sysPlayerId);
        }

        /** @var SysPlayer|null */
        return $this->queryOrMemory()->get((string) $sysPlayerId);
    }

    /**
     * my_idからプレイヤーを検索（キャッシュを通さない）
     *
     * フレンド申請の相手探しなど、他人を引く用途にしか使われない。
     */
    public function selectByMyId(string $myId): ?SysPlayer
    {
        /** @var SysPlayer|null */
        return $this->selectWithoutCache()->where('my_id', $myId)->first();
    }

    /**
     * UUIDからプレイヤーを検索（キャッシュを通さない）
     *
     * サインインの本人確認に使う。この時点ではまだ
     * ログイン中プレイヤーが確定していない。
     */
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        /** @var SysPlayer|null */
        return $this->selectWithoutCache()->where('uuid', $uuid)->first();
    }

    /**
     * my_idが既に存在するかチェック（キャッシュを通さない）
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->selectWithoutCache()->where('my_id', $myId)->exists();
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
        // 全プレイヤーが対象のバッチ用。キャッシュにも更新キューにも載せない
        $query = $this->selectWithoutCache()->where('vip_point', '>=', $minPoint);

        if ($maxPoint !== null) {
            $query->where('vip_point', '<=', $maxPoint);
        }

        $models = $query->orderByDesc('vip_point')
            ->limit($limit)
            ->get();

        /** @var CustomCollection<int, SysPlayer> $collection */
        $collection = new CustomCollection($models->all());

        return $collection;
    }
}
