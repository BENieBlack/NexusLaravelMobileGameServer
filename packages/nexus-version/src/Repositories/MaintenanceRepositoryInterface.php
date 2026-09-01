<?php

namespace NexusVersion\Repositories;

/**
 * MaintenanceRepositoryInterface
 *
 * メンテナンス情報へのアクセスを抽象化
 */
interface MaintenanceRepositoryInterface
{
    /**
     * 現在進行中のメンテナンスを取得
     *
     * @return array|null メンテナンスデータの連想配列、存在しない場合はnull
     * @return array<string, mixed>|null
     */
    public function selectCurrent(): ?array;
}
