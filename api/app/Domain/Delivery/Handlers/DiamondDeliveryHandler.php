<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\InAppPurchase\Services\DiamondService;

/**
 * DiamondDeliveryHandler
 * 
 * ダイヤモンド配送処理を担当するHandler
 * DiamondServiceを使用して、有償/無償ダイヤモンドを加算
 * 
 * Note: platformはmetadataで指定する必要があります
 * metadata['platform']: プラットフォーム（Apple, Google）デフォルトは'Apple'
 * metadata['is_paid']: 有償ダイヤモンドか（true/false）デフォルトはfalse
 */
class DiamondDeliveryHandler implements _BaseDeliveryHandlerInterface
{
    public function __construct(
        private readonly DiamondService $diamondService,
    ) {
    }

    /**
     * ダイヤモンド配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // metadataからplatformとis_paidを取得
        $metadata = $content->getMetadata();
        $platform = $metadata['platform'] ?? 'Apple'; // デフォルトはApple
        $isPaid = $metadata['is_paid'] ?? false; // デフォルトは無償

        // ダイヤモンドを加算
        $this->diamondService->addDiamond(
            $platform,
            $content->getAmount(),
            $isPaid
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ
     * @return bool
     */
    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_DIAMOND;
    }
}
