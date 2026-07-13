<?php

namespace Tests;

use App\Repositories\Mst\_BaseMstRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LaravelUtilities\ClockUtility;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト環境ではクライアント署名検証を無効化
        $this->withoutMiddleware(\LaravelSecurityMiddleware\Middleware\VerifyClientSignature::class);
        
        // Clockをリセット（各テストで独立した時刻を使用）
        ClockUtility::reset();
    }

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
