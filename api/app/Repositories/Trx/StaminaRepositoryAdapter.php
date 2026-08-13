<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;
use NexusStamina\Dto\StaminaDto;
use NexusStamina\Repositories\StaminaRepositoryInterface;

/**
 * StaminaRepositoryAdapter
 *
 * nexus-staminaパッケージのStaminaRepositoryInterfaceを実装し、
 * Application層のTrxStaminaRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 * パッケージ側はApplication層のEloquent Modelに依存できないため、
 * 境界でDTOに詰め替える。
 */
class StaminaRepositoryAdapter implements StaminaRepositoryInterface
{
    public function __construct(
        private readonly TrxStaminaRepository $trxStaminaRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerAndType(int $sysPlayerId, string $type): ?StaminaDto
    {
        $model = $this->trxStaminaRepository->selectByPlayerAndType($sysPlayerId, $type);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(StaminaDto $staminaDto): void
    {
        $model = $this->trxStaminaRepository->selectByPlayerAndType(
            $staminaDto->getSysPlayerId(),
            $staminaDto->getType()
        );

        if ($model === null) {
            return;
        }

        $model->current_stamina = $staminaDto->getCurrentStamina();
        $model->recovery_rate_multiplier = $staminaDto->getRecoveryRateMultiplier();
        $model->last_recovery_at = $staminaDto->getLastRecoveryAt();

        $this->trxStaminaRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(StaminaDto $staminaDto): StaminaDto
    {
        $this->trxStaminaRepository->insertStamina(
            $staminaDto->getSysPlayerId(),
            $staminaDto->getType(),
            $staminaDto->getCurrentStamina(),
            $staminaDto->getRecoveryRateMultiplier(),
            $staminaDto->getLastRecoveryAt()
        );

        return $staminaDto;
    }

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(TrxStamina $model): StaminaDto
    {
        return new StaminaDto(
            sysPlayerId: $model->sys_player_id,
            type: $model->type,
            currentStamina: $model->current_stamina,
            recoveryRateMultiplier: $model->recovery_rate_multiplier,
            lastRecoveryAt: $model->last_recovery_at
        );
    }
}
