<?php

namespace NexusResourceDelivery\Handlers;

use App\Repositories\Trx\TrxUnitRepository;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;

/**
 * UnitDeliveryHandler
 *
 * ユニット配送処理を担当するHandler
 * TrxUnitRepositoryを使用して、新規ユニットを作成
 *
 * 対応リソース:
 * - ResourceType::UNIT
 */
class UnitDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxUnitRepository $trxUnitRepository,
    ) {}

    /**
     * ユニット配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContentDto  $resourceDeliveryContentDto  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContentDto $resourceDeliveryContentDto): void
    {
        // metadataからgradeとlevelを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $resourceDeliveryContentDto->getMetadata();
        $grade = $metadata['grade'] ?? null;
        $level = $metadata['level'] ?? null;

        // 指定された数量分のユニットを作成
        for ($i = 0; $i < $resourceDeliveryContentDto->getAmount(); $i++) {
            $this->trxUnitRepository->createUnit(
                $resourceDeliveryContentDto->getId(),
                $grade,
                $level
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

        return $typeValue === ResourceType::UNIT->value;
    }
}
