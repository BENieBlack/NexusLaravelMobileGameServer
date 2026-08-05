<?php

namespace App\Domain\Player\Services;

use App\Repositories\Trx\TrxEquipmentRepository;
use App\Repositories\Trx\TrxInAppPurchaseEffectRepository;
use App\Repositories\Trx\TrxItemRepository;
use App\Repositories\Trx\TrxPlayerRepository;
use App\Repositories\Trx\TrxPlayerSnsRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Repositories\Trx\TrxUnitRepository;

/**
 * PlayerCleanupService
 * 
 * プレイヤーの論理削除フラグが立っているレコードを物理削除するサービス
 * sign_in時に実行される
 */
class PlayerCleanupService
{
    public function __construct(
        private readonly TrxPlayerRepository $trxPlayerRepository,
        private readonly TrxPlayerSnsRepository $trxPlayerSnsRepository,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxItemRepository $trxItemRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly TrxInAppPurchaseEffectRepository $trxInAppPurchaseEffectRepository,
    ) {
    }

    /**
     * is_delete=trueのレコードを削除キューに追加
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function cleanupDeletedRecords(int $sysPlayerId): void
    {
        // 各テーブルからis_delete=trueのレコードを取得して削除キューに追加
        $this->trxPlayerRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxPlayerSnsRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxUnitRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxItemRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxEquipmentRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxStaminaRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxInAppPurchaseEffectRepository->deleteMarkedRecords($sysPlayerId);
    }
}
