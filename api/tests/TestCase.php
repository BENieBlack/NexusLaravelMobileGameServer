<?php

namespace Tests;

use App\Repositories\Mst\_BaseMstRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mstリポジトリのキャッシュをクリアする
     * テストでマスターデータを作成した後に呼び出すことで、
     * リポジトリが新しいデータを読み込むようにする
     * 
     * @return void
     */
    protected function refreshMstCache(): void
    {
        _BaseMstRepository::clearAllCaches();
    }
}
