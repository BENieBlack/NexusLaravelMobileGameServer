<?php

namespace Tests\Feature\Domain;

use App\Domain\DoesNotReturnUpdatedResources;
use App\Models\Trx\TrxItem;
use App\Repositories\Trx\TrxItemRepository;
use App\Traits\UseCaseTrait;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * updated_resources の返し忘れ検出
 *
 * 画面に出る trx（アイテムなど）を更新したのに、Response が updated_resources を返さない UseCase は、
 * 開発・テスト環境で例外にする。返さない UseCase は #[DoesNotReturnUpdatedResources] で宣言する。
 */
class UpdatedResourcesGuardTest extends TestCase
{
    use RefreshMultipleDatabases;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function tearDown(): void
    {
        DB::connection($this->playerConnection($this->sysPlayerId ?? 0))->table('trx_item')
            ->where('mst_item_id', 'guard_test_item')->delete();

        parent::tearDown();
    }

    private ?int $sysPlayerId = null;

    private function grantItemIn(object $useCase): mixed
    {
        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->useSessionPlayer($player->id);

        $repository = app(TrxItemRepository::class);
        $id = $player->id;

        return $useCase->executeWithTransaction(function () use ($repository, $id) {
            $item = new TrxItem(['sys_player_id' => $id, 'mst_item_id' => 'guard_test_item', 'free_amount' => 1, 'paid_amount' => 0]);
            $item->exists = false;
            $repository->setModel($item);

            return 'done';
        });
    }

    #[Test]
    public function test_updating_without_returning_updated_resources_throws(): void
    {
        $useCase = new class
        {
            use UseCaseTrait;
        };

        $this->expectException(LogicException::class);

        $this->grantItemIn($useCase);
    }

    #[Test]
    public function test_declared_use_case_may_update_without_returning(): void
    {
        $useCase = new #[DoesNotReturnUpdatedResources('テスト')] class
        {
            use UseCaseTrait;
        };

        $this->assertSame('done', $this->grantItemIn($useCase));
    }
}
