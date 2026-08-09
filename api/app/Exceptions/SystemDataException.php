<?php

namespace App\Exceptions;

/**
 * SystemDataException
 *
 * システムデータ（sys database）が見つからない場合の例外クラス
 * 静的ファクトリーメソッドでリソースごとの例外を生成
 *
 * 使用例:
 * - SysDeploy, SysPlayerDevice, SysPlayerToken などが見つからない場合
 * - システム設定やデバイス情報の不整合検出に使用
 */
class SystemDataException extends GameException
{
    /**
     * デプロイ情報が見つからない
     *
     * @param  int|null  $deployId  デプロイID
     */
    public static function deploy(?int $deployId = null): self
    {
        $message = $deployId
            ? "Deploy data not found: {$deployId}"
            : 'No active deploy data found';

        return new self(
            GameErrorCode::INTERNAL_ERROR,
            $message
        );
    }

    /**
     * プレイヤーデバイス情報が見つからない
     *
     * @param  int  $deviceId  デバイスID
     */
    public static function playerDevice(int $deviceId): self
    {
        return new self(
            GameErrorCode::AUTHENTICATION_FAILED,
            "Player device not found: {$deviceId}"
        );
    }

    /**
     * プレイヤートークンが見つからない
     *
     * @param  string  $tokenHash  トークンハッシュ
     */
    public static function playerToken(string $tokenHash): self
    {
        return new self(
            GameErrorCode::INVALID_TOKEN,
            'Player token not found or expired'
        );
    }

    /**
     * システム設定が見つからない
     *
     * @param  string  $key  設定キー
     */
    public static function config(string $key): self
    {
        return new self(
            GameErrorCode::INTERNAL_ERROR,
            "System configuration not found: {$key}"
        );
    }

    /**
     * 汎用システムデータが見つからない
     *
     * @param  string  $type  データ種別（例: "deploy", "device", "token"）
     * @param  string|int  $id  データID
     */
    public static function generic(string $type, string|int $id): self
    {
        return new self(
            GameErrorCode::INTERNAL_ERROR,
            "System data not found: {$type} (ID: {$id})"
        );
    }
}
