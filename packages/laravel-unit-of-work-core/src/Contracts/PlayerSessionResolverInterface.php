<?php

namespace LaravelUnitOfWork\Contracts;

/**
 * PlayerSessionResolverInterface
 * 
 * プレイヤーセッション情報を取得するためのインターフェース
 * アプリケーション側でApiSessionなどの具体的な実装を提供する
 */
interface PlayerSessionResolverInterface
{
    /**
     * プレイヤーIDが設定されているかチェック
     * 
     * @return bool
     */
    public static function hasSysPlayerId(): bool;

    /**
     * プレイヤーIDを取得
     * 
     * @return int
     * @throws \RuntimeException プレイヤーIDが未設定の場合
     */
    public static function getSysPlayerId(): int;

    /**
     * プレイヤーIDを設定
     * 
     * @param int $sysPlayerId
     * @return void
     */
    public static function setSysPlayerId(int $sysPlayerId): void;

    /**
     * データベース接続名を取得（シャーディング対応）
     * 
     * @param string $baseConnection ベースとなる接続名（例: 'trx'）
     * @return string 実際の接続名（例: 'trx1', 'trx2'）
     */
    public static function getConnectionName(string $baseConnection): string;
}
