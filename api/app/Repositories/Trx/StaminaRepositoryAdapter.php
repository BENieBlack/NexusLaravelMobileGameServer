<?php

namespace App\Repositories\Trx;

use App\Adapters\Stamina\StaminaAdapter;
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

        return $model ? StaminaAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Stamina $stamina): void
    {
        $model = $this->trxStaminaRepository->selectByPlayerAndType(
            $stamina->getSysPlayerId(),
            $stamina->getType()
        );

        if ($model === null) {
            return;
        }

        $model->current_stamina = $stamina->getCurrentStamina();
        $model->recovery_rate_multiplier = $stamina->getRecoveryRateMultiplier();
        $model->last_recovery_at = $stamina->getLastRecoveryAt();

        $this->trxStaminaRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(Stamina $stamina): Stamina
    {
        $this->trxStaminaRepository->insertStamina(
            $stamina->getSysPlayerId(),
            $stamina->getType(),
            $stamina->getCurrentStamina(),
            $stamina->getRecoveryRateMultiplier(),
            $stamina->getLastRecoveryAt()
        );

        return $stamina;
    }
}
