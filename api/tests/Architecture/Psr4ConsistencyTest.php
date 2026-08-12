<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PSR-4のパスと名前空間の整合性テスト
 *
 * macOSのファイルシステムは大文字小文字を区別しないため、
 * ディレクトリ名と名前空間の大小が食い違っていてもローカルでは動いてしまう。
 * Linux上のCIや本番環境ではクラスが解決できず fatal になるため、
 * ここで検出する。
 */
class Psr4ConsistencyTest extends TestCase
{
    /**
     * app配下の名前空間宣言が、実際のファイルパスと一致していること
     */
    #[Test]
    public function test_namespace_matches_directory_path(): void
    {
        $appPath = base_path('app');
        $violations = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
                continue;
            }

            $namespace = trim($matches[1]);

            if (! str_starts_with($namespace, 'App\\')) {
                continue;
            }

            // App\Foo\Bar -> app/Foo/Bar
            $relative = str_replace('\\', '/', substr($namespace, strlen('App\\')));
            $expected = $appPath.'/'.$relative.'/'.$file->getBasename('.php').'.php';

            // realpath()は大小を正規化してしまうため、文字列として厳密に比較する
            if ($expected !== $file->getPathname()) {
                $violations[] = sprintf(
                    '%s は %s に置かれるべきです（宣言: %s）',
                    str_replace($appPath.'/', '', $file->getPathname()),
                    str_replace($appPath.'/', '', $expected),
                    $namespace
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "名前空間とディレクトリパスが一致していません。\n".
            "大文字小文字を区別する環境（CI・本番）でクラスが解決できなくなります:\n  ".
            implode("\n  ", $violations)
        );
    }
}
