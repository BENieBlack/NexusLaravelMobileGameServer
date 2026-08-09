<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use NexusAuth\Contracts\PlayerRepositoryInterface as AuthPlayerRepositoryInterface;
use NexusPlayer\Repositories\PlayerRepositoryInterface as PlayerRepoInterface;
use NexusVip\Repositories\PlayerVipRepositoryInterface;
use NexusPlayer\Dto\PlayerDto;
use NexusVip\DTOs\PlayerVipDto;
use NexusUnitOfWork\Contracts\QueryManagerInterface;
use Illuminate\Support\Str;

/**
 * SysPlayerRepository
 *
 * プレイヤー情報のRepository実装
 * 
 * @extends _BaseSysRepository<SysPlayer>
 */
class SysPlayerRepository extends _BaseSysRepository implements AuthPlayerRepositoryInterface, PlayerRepoInterface, PlayerVipRepositoryInterface
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
    public function createPlayerAndCommit(): SysPlayer
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
     * @param int $sysPlayerId プレイヤーID
     * @return SysPlayer|null
     */
    public function selectById(int $sysPlayerId): ?SysPlayer
    {
        // メモリキャッシュから取得
        $sysPlayer = $this->getModel($sysPlayerId);

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
     *
     * @param string $myId
     * @return SysPlayer|null
     */
    public function selectByMyId(string $myId): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->getModels()->firstWhere('my_id', $myId);

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
     *
     * @param string $uuid
     * @return SysPlayer|null
     */
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->getModels()->firstWhere('uuid', $uuid);

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
     *
     * @param string $myId
     * @return bool
     */
    public function existsByMyId(string $myId): bool
    {
        // メモリキャッシュから検索
        if ($this->getModels()->where('my_id', $myId)->isNotEmpty()) {
            return true;
        }

        // DBで確認
        return $this->modelClass::where('my_id', $myId)->exists();
    }

    /**
     * 最終ログイン日時を更新
     *
     * @param SysPlayer $sysPlayer
     * @param \Carbon\CarbonImmutable $loginAt
     * @return void
     */
    public function updateLastLoginAt(SysPlayer $sysPlayer, \Carbon\CarbonImmutable $loginAt): void
    {
        $sysPlayer->last_login_at = $loginAt;
        $this->setModel($sysPlayer);
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerRepositoryInterface実装
     */
    public function findById(int $id): ?PlayerDto
    {
        $model = $this->selectById($id);
        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerRepositoryInterface実装
     */
    public function findByMyId(string $myId): ?PlayerDto
    {
        $model = $this->selectByMyId($myId);
        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerRepositoryInterface実装
     */
    public function findByUuid(string $uuid): ?PlayerDto
    {
        $model = $this->selectByUuid($uuid);
        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     * NexusPlayer\Repositories\PlayerRepositoryInterface実装
     */
    public function save(PlayerDto $playerDto): void
    {
        $model = $this->selectById($playerDto->getId());
        if ($model) {
            // DTOの値をModelに反映
            $model->setName($playerDto->getName());
            $model->setLevel($playerDto->getLevel());
            $model->setLevelExp($playerDto->getLevelExp());
            $this->setModel($model);
        }
    }

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(SysPlayer $model): PlayerDto
    {
        return new PlayerDto(
            id: $model->getId(),
            uuid: $model->getUuid(),
            myId: $model->getMyId(),
            name: $model->getName(),
            level: $model->getLevel(),
            levelExp: $model->getLevelExp(),
            createdAt: $model->getCreatedAt(),
            updatedAt: $model->getUpdatedAt()
        );
    }

    /**
     * {@inheritDoc}
     * NexusVip\Repositories\PlayerVipRepositoryInterface実装
     */
    public function findVipInfoById(int $sysPlayerId): ?PlayerVipDto
    {
        $model = $this->selectById($sysPlayerId);
        return $model ? $this->convertToPlayerVipDto($model) : null;
    }

    /**
     * {@inheritDoc}
     * NexusVip\Repositories\PlayerVipRepositoryInterface実装
     */
    public function saveVipInfo(PlayerVipDto $playerVipDto): void
    {
        $model = $this->selectById($playerVipDto->getSysPlayerId());
        if ($model) {
            // DTOの値をModelに反映
            $model->setVipPoint($playerVipDto->getVipPoint());
            $model->setTotalPaidAmount($playerVipDto->getTotalPaidAmount());
            $this->setModel($model);
        }
    }

    /**
     * {@inheritDoc}
     * NexusVip\Repositories\PlayerVipRepositoryInterface実装
     */
    public function findByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array
    {
        $query = $this->modelClass::where('vip_point', '>=', $minPoint);
        
        if ($maxPoint !== null) {
            $query->where('vip_point', '<=', $maxPoint);
        }
        
        $models = $query->orderByDesc('vip_point')
            ->limit($limit)
            ->get();
        
        return $models->map(fn($model) => $this->convertToPlayerVipDto($model))->all();
    }

    /**
     * Eloquent ModelをPlayerVipDtoに変換
     */
    private function convertToPlayerVipDto(SysPlayer $model): PlayerVipDto
    {
        return new PlayerVipDto(
            sysPlayerId: $model->getId(),
            vipPoint: $model->getVipPoint(),
            totalPaidAmount: $model->getTotalPaidAmount()
        );
    }
}

