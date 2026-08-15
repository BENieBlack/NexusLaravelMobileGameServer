<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;
use NexusStamina\DataTransferObjects\Stamina;
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
    public function selectByPlayerAndType(int $sysPlayerId, string $type): ?Stamina
    {
        $model = $this->trxStaminaRepository->selectByPlayerAndType($sysPlayerId, $type);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Stamina $staminaDto): void
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
    public function insert(Stamina $staminaDto): Stamina
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
    private function convertToDto(TrxStamina $model): Stamina
    {
        return new Stamina(
            sysPlayerId: $model->sys_player_id,
            type: $model->type,
            currentStamina: $model->current_stamina,
            recoveryRateMultiplier: $model->recovery_rate_multiplier,
            lastRecoveryAt: $model->last_recovery_at
        );
    }
}
