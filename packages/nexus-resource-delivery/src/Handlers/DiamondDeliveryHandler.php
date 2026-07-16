<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContent;
use App\Domain\InAppPurchase\Services\DiamondService;

/**
 * DiamondDeliveryHandler
 * 
 * ダイヤモンド配送処理を担当するHandler
 * DiamondServiceを使用して、有償/無償ダイヤモンドを加算
 * 
 * 対応リソース:
 * - ResourceType::DIAMOND (無償ダイヤモンド)
 * - ResourceType::PAID_DIAMOND (有償ダイヤモンド)
 * 
 * Note: platformはmetadataで指定する必要があります
 * metadata['platform']: プラットフォーム（Apple, Google）デフォルトは'Apple'
 */
class DiamondDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly DiamondService $diamondService,
    ) {
    }

    /**
     * ダイヤモンド配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $content): void
    {
        // metadataからplatformを取得
        $metadata = $content->getMetadata();
        $platform = $metadata['platform'] ?? 'Apple'; // デフォルトはApple

        // 有償/無償を判定
        $isPaid = $content->getType() === ResourceType::PAID_DIAMOND;

        // ダイヤモンドを加算
        $this->diamondService->addDiamond(
            $sysPlayerId,
            $platform,
            $content->getAmount(),
            $isPaid
        );
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
        
        return in_array($typeValue, [
            ResourceType::DIAMOND->value,
            ResourceType::PAID_DIAMOND->value,
        ]);
    }
}
