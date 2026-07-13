<?php

namespace NexusMaintenance\Contracts;

use NexusMaintenance\DTOs\SysMaintenance;

/**
 * メンテナンスストレージインターフェース
 * 
 * DynamoDB、TableStoreなど異なるストレージを抽象化
 */
interface MaintenanceStorageInterface
{
    /**
     * メンテナンス情報を取得
     * 
     * @return SysMaintenance|null メンテナンス情報、存在しない場合はnull
     */
    public function get(): ?SysMaintenance;

    /**
     * メンテナンス情報を保存
     * 
     * @param SysMaintenance $sysMaintenance メンテナンス情報
     * @return bool 保存成功時true
     */
    public function put(SysMaintenance $sysMaintenance): bool;

    /**
     * メンテナンス情報を削除（メンテ終了）
     * 
     * @return bool 削除成功時true
     */
    public function delete(): bool;

    /**
     * ストレージの接続確認
     * 
     * @return bool 接続可能な場合true
     */
    public function healthCheck(): bool;
}
