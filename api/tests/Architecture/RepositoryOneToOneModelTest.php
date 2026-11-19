<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: Repository はテーブル（Model）と 1 対 1
 *
 * `api/app/Repositories/{Sys,Mst,Trx,Log}/{Model}Repository` は、同名の Model を持つ。
 * 複数のテーブルを組で読み書きするためだけに Repository を作らない（例: trx と log を組で書く Repository）。
 * 組み合わせは Service（Domain/{Context}/Services/）で行う（.claude/coding-standards.md「Repository」）。
 *
 * 対象は `*Repository.php`。基底（`_Base*`）・Interface・パッケージ契約の実装（`*RepositoryAdapter`）は契約単位なので対象外。
 * 「同名の Model を持つ」の判定: `$modelClass` を宣言していればその短い名前が {Model} と一致すること（Model はパッケージにあってよい）。
 * 宣言が無ければ `api/app/Models/{同じ層}/{Model}.php` があること。
 *
 * 流れ:
 * 1. 対象の Repository を集め、同名の Model を持たないものを違反とする
 * 2. 解消待ちの一覧（PENDING）に無い違反があれば失敗する（新しい違反を足さない）
 * 3. 一覧にあるのに違反でなくなったものがあれば、一覧から消すよう促して失敗する（一覧が縮む）
 */
class RepositoryOneToOneModelTest extends TestCase
{
    /**
     * 解消待ちの違反（層/クラス名）。新しい Repository をここへ足してはならない。解消したら消す。
     * 8 件はパッケージ契約の実装（Adapter 化か Model 付与が必要）。
     * TrxGachaHistoryRepository だけは Interface を実装せず log_action_gacha_draw へ DB::table で直接 INSERT する別種の違反（Trx 接頭辞だが log に書く。本来は Model 付きの Log 側）。
     *
     * @var list<string>
     */
    private const PENDING = [
        'Mst/RewardTrackMasterRepository',
        'Sys/SysChatMessageRepository',
        'Sys/SysChatRoomMemberRepository',
        'Sys/SysChatRoomRepository',
        'Trx/TrxGachaHistoryRepository',
        'Trx/TrxNotificationRepository',
        'Trx/TrxRewardTrackLineRepository',
        'Trx/TrxRewardTrackMilestoneRepository',
        'Trx/TrxRewardTrackRepository',
    ];

    #[Test]
    public function test_repositories_have_the_same_named_model(): void
    {
        $violations = $this->violations();

        $new = array_values(array_diff($violations, self::PENDING));
        $this->assertSame(
            [],
            $new,
            "同名の Model を持たない Repository があります:\n".implode("\n", $new)."\n"
            .'Repository はテーブル（Model）と 1 対 1 です。複数テーブルを組で扱う処理は Service（Domain/{Context}/Services/）に書いてください。'
        );

        $resolved = array_values(array_diff(self::PENDING, $violations));
        $this->assertSame(
            [],
            $resolved,
            "解消済みなので PENDING から消してください:\n".implode("\n", $resolved)
        );
    }

    /**
     * @return list<string> 「層/クラス名」の一覧（昇順）
     */
    private function violations(): array
    {
        $violations = [];

        foreach (['Sys', 'Mst', 'Trx', 'Log'] as $layer) {
            foreach (glob(__DIR__.'/../../app/Repositories/'.$layer.'/*Repository.php') ?: [] as $file) {
                $class = basename($file, '.php');

                if (str_starts_with($class, '_Base')) {
                    continue;
                }

                $model = substr($class, 0, -strlen('Repository'));
                $content = (string) file_get_contents($file);

                $hasModel = preg_match('/\$modelClass = (\w+)::class;/', $content, $declared)
                    ? $declared[1] === $model
                    : is_file(__DIR__.'/../../app/Models/'.$layer.'/'.$model.'.php');

                if (! $hasModel) {
                    $violations[] = $layer.'/'.$class;
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
