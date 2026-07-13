<?php

namespace LaravelUnitOfWork\Contracts;

use LaravelPersistence\Repositories\_BaseRepositoryInterface;

/**
 * QueryManagerInterface
 * 
 * Unit of Workパターンを実装するQueryManagerのインターフェース
 * リポジトリからの操作を収集し、バッチで実行する
 */
interface QueryManagerInterface
{
    /**
     * リポジトリを登録
     * 
     * @param _BaseRepositoryInterface $repository
     * @param bool $isPurchaseLog 課金ログかどうか（ログリポジトリの場合のみ使用）
     * @return void
     */
    public function registerRepository(_BaseRepositoryInterface $repository, bool $isPurchaseLog = false): void;

    /**
     * 登録されたすべてのリポジトリの操作をバッチ実行
     * 
     * @return void
     */
    public function flush(): void;

    /**
     * すべてのリポジトリをクリア
     * 
     * @return void
     */
    public function clear(): void;
}
