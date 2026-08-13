<?php

namespace NexusVersion\Repositories;

/**
 * DeployRepositoryInterface
 * 
 * デプロイ情報へのアクセスを抽象化
 */
interface DeployRepositoryInterface
{
    /**
     * 最新のダウンロード可能なデプロイを取得
     * 
     * @return array|null デプロイデータの連想配列、存在しない場合はnull
     */
    public function selectLatestDownloadable(): ?array;

    /**
     * IDでデプロイを検索
     * 
     * @param int $deployId
     * @return array|null デプロイデータの連想配列、存在しない場合はnull
     */
    public function selectById(int $deployId): ?array;
}
