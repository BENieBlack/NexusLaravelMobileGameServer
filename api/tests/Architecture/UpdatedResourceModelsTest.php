<?php

namespace Tests\Architecture;

use Nexus\Core\UnitOfWork\Contracts\ReturnedAsUpdatedResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: updated_resources でクライアントに返すモデル
 *
 * ReturnedAsUpdatedResource を実装したモデルだけが、updated_resources（trx_item_list など）に入る。
 * 画面に出る状態（所持品・通貨・スタミナ・プレイヤー）に限る。内訳・履歴・内部用のテーブルは入れない。
 * 増やす・減らすときは、このテストの EXPECTED を直し、クライアントに影響することを PR に書く。
 */
class UpdatedResourceModelsTest extends TestCase
{
    /** @var list<string> */
    private const EXPECTED = [
        'App\Models\Sys\SysPlayer',
        'App\Models\Trx\TrxDiamond',
        'App\Models\Trx\TrxEquipment',
        'App\Models\Trx\TrxItem',
        'App\Models\Trx\TrxStamina',
        'App\Models\Trx\TrxUnit',
        'App\Models\Trx\TrxWallet',
    ];

    #[Test]
    public function test_only_expected_models_are_returned_as_updated_resources(): void
    {
        $actual = [];

        foreach (['Sys', 'Mst', 'Trx', 'Log'] as $group) {
            foreach (glob(dirname(__DIR__, 2)."/app/Models/{$group}/*.php") ?: [] as $file) {
                $class = "App\\Models\\{$group}\\".basename($file, '.php');

                if (class_exists($class) && is_subclass_of($class, ReturnedAsUpdatedResource::class)) {
                    $actual[] = $class;
                }
            }
        }

        sort($actual);

        $this->assertSame(
            self::EXPECTED,
            $actual,
            'ReturnedAsUpdatedResource を実装したモデルが、想定と違います。'
            .'内訳・履歴・内部用のテーブルには付けないでください。増減するときは、このテストの EXPECTED を直してください。'
        );
    }
}
