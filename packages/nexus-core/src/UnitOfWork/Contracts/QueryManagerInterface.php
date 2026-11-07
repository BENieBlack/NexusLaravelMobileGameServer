<?php

namespace Nexus\Core\UnitOfWork\Contracts;

use Nexus\Core\Repositories\_BaseRepositoryInterface;
use Nexus\Core\UnitOfWork\UpdatedResources;

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
     * @param  _BaseRepositoryInterface  $repository
     * @param  bool  $isPurchaseLog  課金ログかどうか（ログリポジトリの場合のみ使用）
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
     * ログのみをトランザクション外で実行
     *
     * @return void
     */
    public function execAllLogs(): void;

    /**
     * ここまでに INSERT / UPDATE した trx のモデルを取り出す（取り出すと空に戻る）
     *
     * コミット後に呼ぶ。クライアントに、何が変わったかを返すために使う。
     */
    public function pullUpdatedResources(): UpdatedResources;

    /**
     * 控えた更新を捨てる（ロールバックしたとき）
     */
    public function discardUpdatedResources(): void;

    /**
     * すべてのリポジトリをクリア
     *
     * @return void
     */
    public function clear(): void;
}
