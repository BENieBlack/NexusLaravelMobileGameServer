<?php

namespace Tests\Feature;

use App\Domain\Player\Constants\InitialGrantConst;
use App\Domain\Player\Contracts\InitialGrantInterface;
use App\Domain\Stamina\Constants\StaminaConst;
use App\Models\Sys\SysPlayer;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;
use NexusTidb\Support\TidbMode;
use NexusWallet\Services\WalletCurrencyGranter;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * サインアップ時の初期付与（trx_diamond・trx_wallet・trx_item・trx_stamina）のテスト。
 *
 * 設計: docs/design/home-player-info/{db,sequence}.md（Nexus リポジトリの設計に沿う）。
 * UseCase が自前のトランザクションを持つので、テストでは包まない。
 */
class AuthSignUpInitialGrantTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const TRX_TABLES = ['trx_diamond', 'trx_wallet', 'trx_wallet_balance', 'trx_item', 'trx_stamina'];

    public function beginDatabaseTransaction(): void
    {
        // UseCase がトランザクションを管理するので、自動ラップしない
    }

    protected function tearDown(): void
    {
        // 初期スタミナのテストが書き換えた値を、既定（TestCase::ensureInitialLevelMaster の 50）に戻す
        DB::connection('mst')->table('mst_player_level')->where('level', 1)->update(['max_stamina' => 50]);
        $this->refreshMstCache();
        TidbMode::resetForTest();

        parent::tearDown();
    }

    #[Test]
    public function サインアップ直後に4つのテーブルへ1レコードずつ入る(): void
    {
        ['player' => $player] = $this->signUpPlayer();
        $connection = $this->playerConnection($player->id);

        foreach (['trx_diamond', 'trx_wallet', 'trx_item', 'trx_stamina'] as $table) {
            $this->assertSame(
                1,
                DB::connection($connection)->table($table)->where('sys_player_id', $player->id)->count(),
                "{$table} は 1 レコード"
            );
        }
    }

    #[Test]
    public function trx_diamondは_x_platformのplatformでpaidが0の行になる(): void
    {
        foreach (['Apple', 'Google'] as $platform) {
            $this->withHeader('X-Platform', $platform);
            ['player' => $player] = $this->signUpPlayer();

            $rows = DB::connection($this->playerConnection($player->id))->table('trx_diamond')
                ->where('sys_player_id', $player->id)->get();

            $this->assertCount(1, $rows);
            $this->assertSame($platform, $rows[0]->platform);
            $this->assertSame(0, (int) $rows[0]->paid_amount);
            $this->assertSame(0, (int) $rows[0]->free_amount);
        }
    }

    #[Test]
    public function coinは1000でtrx_walletに入る(): void
    {
        ['player' => $player] = $this->signUpPlayer();

        $row = DB::connection($this->playerConnection($player->id))->table('trx_wallet')
            ->where('sys_player_id', $player->id)->first();

        $this->assertSame(InitialGrantConst::COIN_MST_ITEM_ID, $row->mst_item_id);
        $this->assertSame('coin', $row->mst_item_id);
        $this->assertSame(1000, (int) $row->free_amount);
        $this->assertSame(0, (int) $row->paid_amount);
    }

    #[Test]
    public function 無償ダイヤは50でtrx_itemに入りtrx_walletには入らない(): void
    {
        ['player' => $player] = $this->signUpPlayer();
        $connection = $this->playerConnection($player->id);

        $item = DB::connection($connection)->table('trx_item')->where('sys_player_id', $player->id)->first();

        $this->assertSame('free_diamond', $item->mst_item_id);
        $this->assertSame(50, (int) $item->free_amount);
        $this->assertSame(0, (int) $item->paid_amount);
        $this->assertSame(
            ['coin'],
            DB::connection($connection)->table('trx_wallet')->where('sys_player_id', $player->id)->pluck('mst_item_id')->all()
        );
    }

    #[Test]
    public function 初期スタミナはレベル1の最大値で_type_normal_最終回復は作成時刻(): void
    {
        DB::connection('mst')->table('mst_player_level')->where('level', 1)->update(['max_stamina' => 77]);
        $this->refreshMstCache();

        $before = now()->subSeconds(5);
        ['player' => $player] = $this->signUpPlayer();
        $after = now()->addSeconds(5);

        $row = DB::connection($this->playerConnection($player->id))->table('trx_stamina')
            ->where('sys_player_id', $player->id)->first();

        $this->assertSame(StaminaConst::TYPE_NORMAL, $row->type);
        $this->assertSame(77, (int) $row->current_stamina);
        $this->assertEqualsWithDelta(1.0, (float) $row->recovery_rate_multiplier, 0.001);
        $this->assertGreaterThanOrEqual($before->format('Y-m-d H:i:s'), (string) $row->last_recovery_at);
        $this->assertLessThanOrEqual($after->format('Y-m-d H:i:s'), (string) $row->last_recovery_at);
        $this->assertSame((string) $row->created_at, (string) $row->last_recovery_at, 'last_recovery_at は作成時刻');
    }

    #[Test]
    public function レベル1のマスターが無いとsign_upは失敗し何も残らない(): void
    {
        DB::connection('mst')->table('mst_player_level')->where('level', 1)->delete();
        $this->refreshMstCache();
        $snapshot = $this->snapshot();

        $response = $this->postJson('/api/auth/sign_up', $this->body($deviceId = 'no-master-'.Str::uuid()));

        $this->assertNotSame(200, $response->status(), 'マスター不備は成功にせず、デプロイの不備として失敗させる');
        $this->assertSame($snapshot, $this->snapshot(), '何も残らない');
        $this->assertSame(0, DB::connection('sys')->table('sys_player_device')->where('uuid', $deviceId)->count());
    }

    #[Test]
    public function 付与が失敗するとsign_up全体が失敗し何も残らない(): void
    {
        $snapshot = $this->snapshot();

        // Coin の付与だけが失敗する（先に trx_diamond の付与がキューに載った後で落ちる）
        $this->app->bind(CurrencyGranterInterface::class, fn () => new class implements CurrencyGranterInterface
        {
            public function grantCurrency(int $sysPlayerId, string $currencyId, int $freeAmount, int $paidAmount, ?string $expireAt): void
            {
                throw new \RuntimeException('付与の失敗（テスト）');
            }
        });

        $response = $this->postJson('/api/auth/sign_up', $this->body($deviceId = 'grant-fail-'.Str::uuid()));

        // 失敗が応答に出る（握りつぶされて 200 にならない）。素の RuntimeException なので 500
        $this->assertSame(500, $response->status());
        $this->assertArrayNotHasKey('token', $response->json() ?? []);

        // sys も trx も、行が残らない
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(0, DB::connection('sys')->table('sys_player_device')->where('uuid', $deviceId)->count());
    }

    #[Test]
    public function 付与の失敗の後は同じdevice_idでやり直せる(): void
    {
        $deviceId = 'retry-'.Str::uuid();
        $this->app->bind(CurrencyGranterInterface::class, fn () => new class implements CurrencyGranterInterface
        {
            public function grantCurrency(int $sysPlayerId, string $currencyId, int $freeAmount, int $paidAmount, ?string $expireAt): void
            {
                throw new \RuntimeException('付与の失敗（テスト）');
            }
        });
        $this->postJson('/api/auth/sign_up', $this->body($deviceId))->assertStatus(500);

        // 元の実装に戻して再送する
        $this->app->forgetInstance(CurrencyGranterInterface::class);
        $this->app->bind(CurrencyGranterInterface::class, fn () => $this->app->make(WalletCurrencyGranter::class));
        $response = $this->postJson('/api/auth/sign_up', $this->body($deviceId));

        $response->assertOk();
        $this->trackSignedUp($response->json('sys_player.my_id'));
    }

    #[Test]
    public function 再送の10004では二重付与しない(): void
    {
        $deviceId = 'resend-'.Str::uuid();
        $first = $this->postJson('/api/auth/sign_up', $this->body($deviceId));
        $first->assertOk();
        $player = $this->trackSignedUp($first->json('sys_player.my_id'));
        $connection = $this->playerConnection($player->id);
        $snapshot = $this->snapshot();

        $second = $this->postJson('/api/auth/sign_up', $this->body($deviceId));

        $second->assertStatus(299);
        $second->assertJsonPath('error_code', 10004);
        $this->assertSame($snapshot, $this->snapshot(), '2 回目は何も書かない');
        $this->assertSame(
            1000,
            (int) DB::connection($connection)->table('trx_wallet')->where('sys_player_id', $player->id)->value('free_amount')
        );
        $this->assertSame(
            50,
            (int) DB::connection($connection)->table('trx_item')->where('sys_player_id', $player->id)->value('free_amount')
        );
    }

    #[Test]
    public function sign_inでは付与されない(): void
    {
        $deviceId = 'sign-in-'.Str::uuid();
        $first = $this->postJson('/api/auth/sign_up', $this->body($deviceId));
        $first->assertOk();
        $player = $this->trackSignedUp($first->json('sys_player.my_id'));
        $snapshot = $this->snapshot();

        $this->postJson('/api/auth/sign_in', ['device_id' => $deviceId, 'device_info' => ['os' => 'iOS', 'os_version' => '17.0', 'model' => 'iPhone', 'app_version' => '1.0.0']])->assertOk();

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(
            1,
            DB::connection($this->playerConnection($player->id))->table('trx_stamina')->where('sys_player_id', $player->id)->count()
        );
    }

    /**
     * TiDB モードの検証は「可能な範囲」。テスト DB は MySQL のスキーマ（trx の id が bigint）なので、
     * TiDB モードで本物の付与を流すと uuid の id が入らず落ちる（trx_wallet_balance）。
     * そこで付与を記録だけの代役に差し替え、付与の時点で「割り当てを作らず、書き込み先が trx1」に
     * なっていることを確かめる。付与そのものの中身は、MySQL モードのテストで確かめている。
     */
    #[Test]
    public function tidbモードではシャード割り当てを作らず付与の時点で書き込み先がtrx1になる(): void
    {
        TidbMode::fakeForTest(true);

        $seen = new \stdClass;
        $this->app->bind(InitialGrantInterface::class, fn () => new class($seen) implements InitialGrantInterface
        {
            public function __construct(private \stdClass $seen) {}

            public function grant(int $sysPlayerId, string $platform): void
            {
                $this->seen->playerId = $sysPlayerId;
                $this->seen->platform = $platform;
                $this->seen->connection = ApiSession::resolveConnectionName();
            }
        });

        $response = $this->postJson('/api/auth/sign_up', $this->body('tidb-'.Str::uuid()));
        $response->assertOk();
        $player = SysPlayer::where('my_id', $response->json('sys_player.my_id'))->firstOrFail();

        try {
            $this->assertSame($player->id, $seen->playerId);
            $this->assertSame('Apple', $seen->platform);
            $this->assertSame('trx1', $seen->connection);
            $this->assertSame(
                0,
                DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', $player->id)->count(),
                'TiDB モードでは割り当てを作らない'
            );
        } finally {
            foreach (['sys_player_device', 'sys_player_token'] as $table) {
                DB::connection('sys')->table($table)->where('sys_player_id', $player->id)->delete();
            }
            DB::connection('sys')->table('sys_player')->where('id', $player->id)->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $deviceId): array
    {
        return ['device_id' => $deviceId, 'device_info' => ['os' => 'iOS']];
    }

    /**
     * 直接 sign_up を叩いたプレイヤーを、テスト終了時の後始末（sys と trx の行の削除）の対象にする
     */
    private function trackSignedUp(string $myId): SysPlayer
    {
        $player = SysPlayer::where('my_id', $myId)->firstOrFail();
        $ids = new \ReflectionProperty(TestCase::class, 'signedUpPlayerIds');
        $ids->setValue($this, [...$ids->getValue($this), $player->id]);

        return $player;
    }

    /**
     * 書き込み先になりうる全テーブルの件数（何も残らないことの検証用）
     *
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        $counts = [];

        foreach (['sys_player', 'sys_player_device', 'sys_player_token', 'sys_sharding_node_player'] as $table) {
            $counts["sys.{$table}"] = DB::connection('sys')->table($table)->count();
        }

        foreach (['trx1', 'trx2'] as $connection) {
            foreach (self::TRX_TABLES as $table) {
                $counts["{$connection}.{$table}"] = DB::connection($connection)->table($table)->count();
            }
        }

        return $counts;
    }
}
