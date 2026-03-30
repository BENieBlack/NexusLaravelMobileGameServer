<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxUnit;
use App\Repositories\Log\LogUnitRepository;
use App\Utilities\ApiSession;
use App\Utilities\Clock;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * TrxUnitRepository
 *
 * プレイヤーが所持するユニット（キャラクター）を管理するRepository
 * QueryManager保存時にLogUnitRepositoryへログを記録する
 */
class TrxUnitRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxUnit::class;

    /**
     * LogUnitRepository（ログ連動用）
     */
    private ?LogUnitRepository $logUnitRepository = null;

    /**
     * unique_request_id（ログ記録用）
     */
    private ?string $uniqueRequestId = null;

    /**
     * LogUnitRepositoryを設定
     *
     * @param LogUnitRepository $logUnitRepository
     * @return void
     */
    public function setLogRepository(LogUnitRepository $logUnitRepository): void
    {
        $this->logUnitRepository = $logUnitRepository;
    }

    /**
     * UniqueRequestIdを設定
     *
     * @param string $uniqueRequestId
     * @return void
     */
    public function setUniqueRequestId(string $uniqueRequestId): void
    {
        $this->uniqueRequestId = $uniqueRequestId;
    }

    /**
     * モデル保存後のフック - ログを記録
     * QueryManagerがsave時に呼び出す
     *
     * @param TrxUnit $model
     * @param array<string, mixed> $originalState
     * @return void
     */
    public function afterSave($model, array $originalState): void
    {
        // LogUnitRepositoryが設定されていれば、ログを記録
        if ($this->logUnitRepository !== null && $this->uniqueRequestId !== null) {
            $beforeGrade = $originalState['grade'] ?? $model->getGrade();
            $beforeLevel = $originalState['level'] ?? $model->getLevel();
            $beforeLevelExp = $originalState['level_exp'] ?? $model->getLevelExp();

            $this->logUnitRepository->createUnitLog(
                $this->uniqueRequestId,
                $model->getSysPlayerId(),
                $model->getId(),
                $model->getMstUnitId(),
                $beforeGrade,
                $model->getGrade(),
                $beforeLevel,
                $model->getLevel(),
                $beforeLevelExp,
                $model->getLevelExp()
            );
        }
    }

    /**
     * IDでユニットを検索
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @return TrxUnit|null ユニット（見つからない場合はnull）
     */
    public function selectById(int $trxUnitId): ?TrxUnit
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $this->queryOrMemory();
        
        // キャッシュから取得
        return $this->getModel($trxUnitId);
    }

    /**
     * ユニットのレベルを上げる（例）
     *
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @param int $expToAdd
     * @return void
     */
    public function addExp(int $trxUnitId, int $expToAdd): void
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $this->queryOrMemory();
        
        $trxUnit = $this->getModel($trxUnitId);

        if ($trxUnit) {
            $trxUnit->setLevelExp($trxUnit->getLevelExp() + $expToAdd);

            // レベルアップ処理（仮）
            while ($trxUnit->getLevelExp() >= 100) {
                $trxUnit->setLevel($trxUnit->getLevel() + 1);
                $trxUnit->setLevelExp($trxUnit->getLevelExp() - 100);
            }

            // setModelでupdated_at自動設定、Trxデータをキューイング
            $this->setModel($trxUnit);
        }
    }

    /**
     * ユニットのグレードを上げる（例）
     *
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @return void
     */
    public function upgradeGrade(int $trxUnitId): void
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $this->queryOrMemory();
        
        $trxUnit = $this->getModel($trxUnitId);

        if ($trxUnit) {
            $trxUnit->setGrade($trxUnit->getGrade() + 1);

            // setModelでupdated_at自動設定、Trxデータをキューイング
            $this->setModel($trxUnit);
        }
    }

    /**
     * 新規ユニットを作成
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstUnitId ユニットマスターID
     * @param int|null $grade 初期グレード（nullの場合は1）
     * @param int|null $level 初期レベル（nullの場合は1）
     * @return TrxUnit 作成されたユニット
     */
    public function createUnit(
        string $mstUnitId,
        ?int $grade = null,
        ?int $level = null
    ): TrxUnit {
        $sysPlayerId = ApiSession::getSysPlayerId();
        
        $trxUnit = new TrxUnit([
            'sys_player_id' => $sysPlayerId,
            'mst_unit_id' => $mstUnitId,
            'grade' => $grade ?? 1,
            'level' => $level ?? 1,
            'level_exp' => 0,
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        // setModelでTrxデータをキューイング
        $this->setModel($trxUnit);

        return $trxUnit;
    }
}
