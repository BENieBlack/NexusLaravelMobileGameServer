<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: updated_resources を返す Response のそろえ方
 *
 * executeWithTransaction() は、ReturnsUpdatedResources を実装した Response にだけ更新を付ける。
 * 片方だけ書くと、updated_resources が空のまま返ったり、付いたのに出力されなかったりする。
 */
class UpdatedResourcesResponseTest extends TestCase
{
    #[Test]
    public function test_interface_trait_and_output_key_come_together(): void
    {
        $violations = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Domain/*/Responses/*.php') ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $name = basename(dirname($file, 2)).'/Responses/'.basename($file);

            $implements = (bool) preg_match('/class\s+\w+[^{]*\bimplements\b[^{]*\bReturnsUpdatedResources\b/', $content);
            $usesTrait = (bool) preg_match('/^\s*use\s+HasUpdatedResources\s*;/m', $content);
            $outputs = str_contains($content, "'updated_resources'");

            if (! $implements && ! $usesTrait && ! $outputs) {
                continue;
            }

            if (! $implements) {
                $violations[] = "{$name}: ReturnsUpdatedResources を implements してください";
            }
            if (! $usesTrait) {
                $violations[] = "{$name}: HasUpdatedResources を use してください";
            }
            if (! $outputs) {
                $violations[] = "{$name}: toArray() に 'updated_resources' が無く、更新を返せません";
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
