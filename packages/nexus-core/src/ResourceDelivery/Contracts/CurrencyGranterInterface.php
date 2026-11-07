<?php

namespace Nexus\Core\ResourceDelivery\Contracts;

/**
 * CurrencyGranterInterface
 *
 * 通貨・ポイント・自然資源（残高として持つリソース）の付与を抽象化するインターフェース
 * 実装は nexus-wallet が持つ（Wallet は取得単位の有効期限と先入先出の消費ができる残高の仕組み）。
 *
 * nexus-core が nexus-wallet に依存すると、nexus-wallet が nexus-core に依存しているため循環する。
 * また、nexus-wallet を外したときに nexus-core 全体が壊れる。そのため、配送のハンドラーは
 * この契約だけに依存する。
 *
 * 付与先のプレイヤーは引数で受け取る。配送はログインセッションの本人以外
 * （運営からの一斉配布など）にも走りうるため、暗黙のセッション参照にしない。
 */
interface CurrencyGranterInterface
{
    /**
     * 通貨を付与する
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  string  $currencyId  通貨のID（mst_item.id。mst_item.is_wallet = true のもの）
     * @param  int  $freeAmount  無償の付与量
     * @param  int  $paidAmount  有償の付与量
     * @param  string|null  $expireAt  有効期限（Y-m-d H:i:s形式）。NULLは無期限
     */
    public function grantCurrency(
        int $sysPlayerId,
        string $currencyId,
        int $freeAmount,
        int $paidAmount,
        ?string $expireAt,
    ): void;
}
