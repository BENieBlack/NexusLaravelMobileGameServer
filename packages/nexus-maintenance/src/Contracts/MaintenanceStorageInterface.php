<?php

namespace NexusMaintenance\Contracts;

use NexusMaintenance\DTOs\MaintenanceDto;

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
     * @return MaintenanceDto|null メンテナンス情報、存在しない場合はnull
     */
    public function get(): ?MaintenanceDto;

    /**
     * メンテナンス情報を保存
     * 
     * @param MaintenanceDto $sysMaintenance メンテナンス情報
     * @return bool 保存成功時true
     */
    public function put(MaintenanceDto $sysMaintenance): bool;

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
