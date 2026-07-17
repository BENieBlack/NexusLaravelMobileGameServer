<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;
use NexusStamina\Repositories\StaminaRepositoryInterface;
use NexusStamina\Dto\StaminaDto;
use App\Persistence\ApiSession;

/**
 * TrxStaminaRepository
 * 
 * スタミナデータの永続化のみを担当
 * ビジネスロジックはStaminaServiceに実装
 * 
 * PRIMARY KEY: (sys_player_id, type)
 * 
 * @extends _BaseTrxRepository<TrxStamina>
 */
class TrxStaminaRepository extends _BaseTrxRepository implements StaminaRepositoryInterface
{
    protected string $modelClass = TrxStamina::class;
    protected string $selectKey = 'sys_player_id';

    public function __construct(
        private readonly ApiSession $apiSession
    ) {
    }

    /**
     * プレイヤーの指定タイプのスタミナ情報を取得
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param string $type スタミナタイプ
     * @return TrxStamina|null
     */
    public function selectByType(string $type): ?TrxStamina
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $modelCollection = $this->queryOrMemory();
        
        // typeでフィルタして取得
        /** @var TrxStamina|null */
        return $modelCollection->where('type', $type)->first();
    }

    /**
     * プレイヤーの全てのスタミナ情報を取得
     *
     * @return \Illuminate\Support\Collection<int, TrxStamina>
     */
    public function selectAllByPlayer(): \Illuminate\Support\Collection
    {
        // queryOrMemory()で全データをキャッシュにロード
        return $this->queryOrMemory();
    }

    /**
     * {@inheritDoc}
     * StaminaRepositoryInterface実装
     */
    public function findByPlayerAndType(int $sysPlayerId, string $type): ?StaminaDto
    {
        // ApiSessionを一時的に設定
        $originalPlayerId = $this->apiSession->getSysPlayerId();
        $this->apiSession->setSysPlayerId($sysPlayerId);

        $stamina = $this->selectByType($type);

        // ApiSessionを元に戻す
        if ($originalPlayerId !== null) {
            $this->apiSession->setSysPlayerId($originalPlayerId);
        }

        if (!$stamina) {
            return null;
        }

        return new StaminaDto(
            sysPlayerId: $stamina->sys_player_id,
            type: $stamina->type,
            currentStamina: $stamina->current_stamina,
            recoveryRateMultiplier: $stamina->recovery_rate_multiplier,
            lastRecoveryAt: $stamina->last_recovery_at
        );
    }

    /**
     * {@inheritDoc}
     * StaminaRepositoryInterface実装
     */
    public function save(StaminaDto $staminaDto): void
    {
        $stamina = $this->selectByType($staminaDto->getType());
        
        if ($stamina) {
            $stamina->current_stamina = $staminaDto->getCurrentStamina();
            $stamina->recovery_rate_multiplier = $staminaDto->getRecoveryRateMultiplier();
            $stamina->last_recovery_at = $staminaDto->getLastRecoveryAt();
            $this->setModel($stamina);
        }
    }

    /**
     * {@inheritDoc}
     * StaminaRepositoryInterface実装
     */
    public function create(StaminaDto $staminaDto): StaminaDto
    {
        $stamina = new TrxStamina([
            'sys_player_id' => $staminaDto->getSysPlayerId(),
            'type' => $staminaDto->getType(),
            'current_stamina' => $staminaDto->getCurrentStamina(),
            'recovery_rate_multiplier' => $staminaDto->getRecoveryRateMultiplier(),
            'last_recovery_at' => $staminaDto->getLastRecoveryAt(),
        ]);
        $stamina->exists = false;
        $this->setModel($stamina);
        
        return $staminaDto;
    }
}

