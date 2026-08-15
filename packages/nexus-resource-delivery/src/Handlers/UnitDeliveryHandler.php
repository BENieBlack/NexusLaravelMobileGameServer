<?php

namespace NexusResourceDelivery\Handlers;

use App\Repositories\Trx\TrxUnitRepository;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

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
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // metadataからgradeとlevelを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $resourceDeliveryContent->getMetadata();
        $grade = $metadata['grade'] ?? null;
        $level = $metadata['level'] ?? null;

        // 指定された数量分のユニットを作成
        for ($i = 0; $i < $resourceDeliveryContent->getAmount(); $i++) {
            $this->trxUnitRepository->insertUnit(
                $resourceDeliveryContent->getId(),
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
