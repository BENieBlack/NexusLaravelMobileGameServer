<?php

namespace NexusSecurity\Contracts;

/**
 * PlayerSessionInterface
 *
 * プレイヤーセッション情報を管理するインターフェース
 * アプリケーション側で実装する必要があります
 */
interface PlayerSessionInterface
{
    /**
     * プレイヤーIDを設定する
     *
     * @param  int  $playerId  プレイヤーID
     * @return void
     */
    public static function setPlayerId(int $playerId): void;
}
