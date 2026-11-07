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
        $trxStamina = $this->trxStaminaRepository->selectByPlayerAndType($sysPlayerId, $type);

        return $trxStamina ? StaminaAdapter::toDto($trxStamina) : null;
    }

    /**
     * {@inheritDoc}
     *
     * ApiSession のプレイヤー（リクエストしたプレイヤー）の全タイプを返す。
     */
    public function selectAllByPlayer(int $sysPlayerId): array
    {
        $staminas = [];

        foreach ($this->trxStaminaRepository->selectAllByPlayer() as $trxStamina) {
            if ($trxStamina->getSysPlayerId() === $sysPlayerId) {
                $staminas[] = StaminaAdapter::toDto($trxStamina);
            }
        }

        return $staminas;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Stamina $stamina): void
    {
        $trxStamina = $this->trxStaminaRepository->selectByPlayerAndType(
            $stamina->getSysPlayerId(),
            $stamina->getType()
        );

        if ($trxStamina === null) {
            return;
        }

        $trxStamina->setCurrentStamina($stamina->getCurrentStamina());
        $trxStamina->setRecoveryRateMultiplier($stamina->getRecoveryRateMultiplier());
        $trxStamina->setLastRecoveryAt($stamina->getLastRecoveryAt());

        $this->trxStaminaRepository->setModel($trxStamina);
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
