<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Repositories\Trx\TrxEquipmentRepository;

/**
 * EquipmentDeliveryHandler
 * 
 * 装備配送処理を担当するHandler
 * TrxEquipmentRepositoryを使用して、新規装備を作成
 */
class EquipmentDeliveryHandler implements _BaseDeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {
    }

    /**
     * 装備配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // metadataからlevel/gradeを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $content->getMetadata();
        $level = $metadata['level'] ?? null;
        $grade = $metadata['grade'] ?? null;

        // 指定された数量分の装備を作成
        for ($i = 0; $i < $content->getAmount(); $i++) {
            $this->trxEquipmentRepository->createEquipment(
                $content->getId(),
                $level,
                $grade
            );
        }
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ
     * @return bool
     */
    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_EQUIPMENT;
    }
}
