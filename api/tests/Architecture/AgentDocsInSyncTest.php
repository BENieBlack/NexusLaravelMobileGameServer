<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: エージェント向けドキュメントの同期
 *
 * .claude と .opencode は同じ内容を両ツールに見せる必要がある。
 * 以前は実体を2つ持って差分を検出していたが、17,671行の完全な複製で
 * 片方だけ更新される事故が起きるため、.opencode を .claude への
 * シンボリックリンクにして実体を1つにした（2026-08-20）。
 *
 * ここではリンクが壊れていないことを検証する。
 * 実体をコピーに戻すと、このテストが落ちて気づける。
 */
class AgentDocsInSyncTest extends TestCase
{
    #[Test]
    public function test_opencode_is_symlink_to_claude(): void
    {
        $root = dirname(__DIR__, 3);
        $opencode = $root.'/.opencode';

        // コンテナ内はapiと.claudeだけをマウントしており、リポジトリの実体が無い。
        // シンボリックリンクかどうかを見られるのはチェックアウト上だけなので、
        // .git の有無で判定して検証対象を絞る（CIはチェックアウト上で実行される）。
        if (! is_dir($root.'/.git')) {
            $this->markTestSkipped('リポジトリのチェックアウト上でのみ検証する');
        }

        $this->assertTrue(
            is_link($opencode),
            '.opencode は .claude へのシンボリックリンクにしてください。'
            .'実体をコピーで持つと、片方だけ更新されて内容が食い違います。'
        );

        $this->assertSame(
            '.claude',
            readlink($opencode),
            '.opencode のリンク先は .claude にしてください'
        );

        $this->assertDirectoryExists($opencode, '.opencode のリンクが切れています');
        $this->assertFileExists($opencode.'/README.md', '.opencode 経由でドキュメントが読めません');
    }
}
