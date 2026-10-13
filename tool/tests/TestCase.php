<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * sys_player が無ければ、toolが読む列だけで作る
     *
     * 本来のスキーマは api 側のマイグレーションが持つ。CIの tool ジョブは api を
     * インストールしないため、テストに必要な最小限をここで用意する。
     * 既にテーブルがある環境（apiでマイグレーション済みのローカル）では何もしない。
     */
    protected function ensureSysPlayerTable(): void
    {
        DB::connection('sys')->statement(
            'CREATE TABLE IF NOT EXISTS sys_player ('
            .'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
            .'uuid VARCHAR(64) NOT NULL UNIQUE, '
            .'my_id VARCHAR(8) NOT NULL UNIQUE, '
            .'name VARCHAR(100) NULL, '
            .'level INT UNSIGNED NOT NULL DEFAULT 1, '
            .'last_login_at DATETIME NULL, '
            .'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            .'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            .')'
        );
    }
}
