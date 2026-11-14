<?php

namespace Nexus\Core\ResourceDelivery\Handlers;

use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\Resource\Services\DiamondService;
use Nexus\Core\ResourceDelivery\Contracts\DefaultPlatformProviderInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

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
 * platform の決め方（優先順）:
 * 1. metadata['platform']（Apple, Google）。課金など呼び出し側が明示するとき
 * 2. 現在の処理の platform（DefaultPlatformProviderInterface。API では X-Platform の値）
 * 3. 'Apple'（ジョブ・バッチ・CLI など platform の文脈が無い経路。従来どおり）
 */
class DiamondDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly DiamondService $diamondService,
        // 省略時は従来どおり Apple。Laravel を使わない利用（standalone）でも組み立てられるよう任意にする
        private readonly ?DefaultPlatformProviderInterface $defaultPlatformProvider = null,
    ) {}

    /**
     * ダイヤモンド配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // platform を決める。metadata の明示を最優先し、無ければ現在の処理の platform
        // （API では X-Platform。login が端末の platform の行だけを返すので、同じ行に入れて見えるようにする）、
        // それも無ければ従来どおり Apple
        $metadata = $resourceDeliveryContent->getMetadata();
        $platform = $metadata['platform']
            ?? $this->defaultPlatformProvider?->findCurrentPlatform()
            ?? 'Apple';

        // 有償/無償を判定
        $isPaid = $resourceDeliveryContent->getType() === ResourceType::PAID_DIAMOND;

        // ダイヤモンドを加算
        $this->diamondService->addDiamond(
            $sysPlayerId,
            $platform,
            $resourceDeliveryContent->getAmount(),
            $isPaid
        );
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
            ResourceType::DIAMOND->value,
            ResourceType::PAID_DIAMOND->value,
        ]);
    }
}
