<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use App\Repositories\Trx\TrxUnitRepository;

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
    ) {
    }

    /**
     * ユニット配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryContentDto $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContentDto $content): void
    {
        // metadataからgradeとlevelを取得（指定がない場合はnull = デフォルト値を使用）
        $metadata = $content->getMetadata();
        $grade = $metadata['grade'] ?? null;
        $level = $metadata['level'] ?? null;

        // 指定された数量分のユニットを作成
        for ($i = 0; $i < $content->getAmount(); $i++) {
            $this->trxUnitRepository->createUnit(
                $content->getId(),
                $grade,
                $level
            );
        }
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param ResourceType|string $type リソースタイプ
     * @return bool
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;
        
        return $typeValue === ResourceType::UNIT->value;
    }
}
