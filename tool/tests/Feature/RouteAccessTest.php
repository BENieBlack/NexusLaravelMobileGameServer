<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ツールのルート保護のテスト
 *
 * マスターインポートは mst テーブルを丸ごと入れ替える操作のため、
 * 未認証で触れる状態になっていないことを固定する。
 * ルートの並べ替えや middleware の付け替えで穴が開いたら、ここが落ちる。
 */
class RouteAccessTest extends TestCase
{
    #[Test]
    public function 未認証ならログイン画面へ飛ばす(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function 未認証ではダッシュボードを開けない(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function 未認証ではマスターインポートを開けない(): void
    {
        $this->get('/master-import')->assertRedirect('/login');
    }

    #[Test]
    public function 未認証でもマスターインポートを実行できない(): void
    {
        $this->post('/master-import/execute')->assertRedirect('/login');
    }
}
