<?php

namespace App\Domain\Player\Constants;

use Nexus\Core\Resource\Constants\FreeDiamondConst;

/**
 * サインアップ時の初期付与の値
 *
 * 変更する運用が想定できないため MstConfig には持たず、ここ 1 か所にまとめる
 * （設計 home-player-info の db.md 8.2 節 Q15）。付与の Service とテストはこの定数を参照し、
 * 数値を複数箇所に書かない。
 *
 * 初期スタミナはここに持たない。レベル 1 の mst_player_level.max_stamina が定義そのもの
 * なので、マスターから引く（二重管理を避ける）。
 */
final class InitialGrantConst
{
    /**
     * 初期 Coin の量
     */
    public const COIN_AMOUNT = 1000;

    /**
     * Coin の mst_item.id（ResourceType::COIN の値と同じ）
     */
    public const COIN_MST_ITEM_ID = 'coin';

    /**
     * 初期の無償ダイヤの量（trx_item の free_diamond の free_amount に入る）
     */
    public const FREE_DIAMOND_AMOUNT = 50;

    /**
     * 無償ダイヤの mst_item.id（パッケージ側の定数を指す。ItemService のガードと同じ ID を使うため）
     */
    public const FREE_DIAMOND_MST_ITEM_ID = FreeDiamondConst::MST_ITEM_ID;

    /**
     * 初期スタミナの元になるレベル
     */
    public const INITIAL_LEVEL = 1;

    private function __construct() {}
}
