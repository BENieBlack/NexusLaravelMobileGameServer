<?php

namespace NexusUnitOfWork\Traits;

use NexusUnitOfWork\Contracts\QueryManagerInterface;

/**
 * UsesUnitOfWork
 * 
 * RepositoryクラスでUnit of Workパターンを使用するためのTrait
 * setModel()をオーバーライドしてQueryManagerに自動登録する
 */
trait UsesUnitOfWork
{
    /**
     * QueryManagerに登録済みかどうか
     *
     * @var bool
     */
    protected bool $registeredToQueryManager = false;

    /**
     * モデルをセットし、QueryManagerに登録する
     * 
     * @param mixed $model
     * @param bool|null $isPurchaseLog 課金ログかどうか（Logリポジトリの場合のみ使用）
     * @return void
     */
    public function setModel($model, ?bool $isPurchaseLog = null): void
    {
        // 親クラスのsetModelを呼び出し（キューに追加）
        parent::setModel($model);

        // QueryManagerに自身を登録（初回のみ）
        if (!$this->registeredToQueryManager) {
            $queryManager = app()->make(QueryManagerInterface::class);
            
            // Logリポジトリの場合は$isPurchaseLogパラメータを渡す
            if ($isPurchaseLog !== null) {
                $queryManager->registerRepository($this, $isPurchaseLog);
            } else {
                $queryManager->registerRepository($this);
            }
            
            $this->registeredToQueryManager = true;
        }
    }

    /**
     * キューに積んだクエリを即座に実行する
     *
     * INSERTで採番された主キーを直後に参照する必要がある場合にのみ使用する。
     * 通常はUseCaseのトランザクション終了時に一括でフラッシュされるため、
     * 呼び出す必要はない。
     *
     * トランザクション内で呼ばれる前提であり、実行後はキューがクリアされる。
     *
     * @return void
     */
    public function flushQueue(): void
    {
        app()->make(QueryManagerInterface::class)->flush();
    }

    /**
     * キューをクリアし、登録フラグをリセット
     *
     * @return void
     */
    public function clearQueue(): void
    {
        parent::clearQueue();
        $this->registeredToQueryManager = false;
    }
}
