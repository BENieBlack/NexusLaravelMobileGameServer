<?php

namespace NexusResourceDelivery\Contracts;

/**
 * ItemGranterInterface
 *
 * アイテム付与を抽象化するインターフェース
 * 実装はApplication層で行い、パッケージ層はこれに依存する
 *
 * アイテムを trx_item で持つか Wallet の残高として持つかは
 * mst_item.is_wallet で決まる。この判定はマスターを読む必要があり
 * Application層にしか置けないため、配送側は経路を指定せずここへ委ねる。
 *
 * 付与先のプレイヤーは引数で受け取る。配送はログインセッションの本人以外
 * （運営からの一斉配布など）にも走りうるため、暗黙のセッション参照にしない。
 */
interface ItemGranterInterface
{
    /**
     * アイテムを付与する
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  string  $mstItemId  mst_item.id
     * @param  int  $amount  付与量
     * @param  string|null  $expireAt  有効期限（Wallet管理のアイテムのみ。nullなら無期限）
     */
    public function grantItem(int $sysPlayerId, string $mstItemId, int $amount, ?string $expireAt = null): void;
}
