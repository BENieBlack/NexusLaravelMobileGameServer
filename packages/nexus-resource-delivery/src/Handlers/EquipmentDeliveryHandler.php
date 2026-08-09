<?php

namespace NexusResourceDelivery\Handlers;

use App\Repositories\Trx\TrxEquipmentRepository;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;

/**
 * EquipmentDeliveryHandler
 *
 * 装備配送処理を担当するHandler
 * TrxEquipmentRepositoryを使用して、新規装備を作成
 *
 * 対応リソース:
 * - ResourceType::EQUIPMENT
 * - ResourceType::WEAPON
 * - ResourceType::ARMOR
 * - ResourceType::ACCESSORY
 */
class EquipmentDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {}

    /**
     * 装備配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContentDto  $resourceDeliveryContentDto  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContentDto $resourceDeliveryContentDto): void
    {
        // metadataからlevel/gradeを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $resourceDeliveryContentDto->getMetadata();
        $level = $metadata['level'] ?? null;
        $grade = $metadata['grade'] ?? null;

        // 指定された数量分の装備を作成
        for ($i = 0; $i < $resourceDeliveryContentDto->getAmount(); $i++) {
            $this->trxEquipmentRepository->createEquipment(
                $resourceDeliveryContentDto->getId(),
                $level,
                $grade
            );
        }
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;

        return in_array($typeValue, [
            ResourceType::EQUIPMENT->value,
            ResourceType::WEAPON->value,
            ResourceType::ARMOR->value,
            ResourceType::ACCESSORY->value,
        ]);
    }
}
