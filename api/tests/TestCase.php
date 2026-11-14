<?php

namespace Tests;

use App\Models\Sys\SysPlayer;
use App\Persistence\ApiSession;
use App\Repositories\Mst\_BaseMstRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Pitr\Logger\ShardMapper;
use Nexus\Core\UnitOfWork\Contracts\QueryManagerInterface;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusSecurity\Middleware\VerifyClientSignature;
use Tests\Support\InMemoryMaintenanceStorage;

abstract class TestCase extends BaseTestCase
{
    /**
     * signUpPlayer() で作ったプレイヤー（テストの終わりに消す）
     *
     * @var list<int>
     */
    private array $signedUpPlayerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // テスト環境ではクライアント署名検証を無効化
        $this->withoutMiddleware(VerifyClientSignature::class);

        // 全ゲームAPIは X-Platform ヘッダーが必須なので、既定で Apple を付ける。
        // 除外ルート・欠落・不正値のテストだけ withoutHeader('X-Platform') で外す
        $this->withHeader('X-Platform', 'Apple');

        // メンテナンスストレージをメモリ実装に差し替える
        // （本番ドライバはAWS/Alibabaの外部SDKを必要とするためテストでは使えない）
        $this->app->singleton(
            MaintenanceStorageInterface::class,
            fn () => new InMemoryMaintenanceStorage
        );

        // テストではマスターデータを組み立てる必要があるため書き込みを許可する
        // （本番の実行時経路では _BaseMst が書き込みを拒否する）
        _BaseMst::allowWrites();

        // テストのフィクスチャはUnitOfWorkを介さずModelを直接投入するため許可する
        // （本番の実行時経路では _BaseModel が save() を拒否する）
        _BaseModel::allowDirectWrites();

        // Clockをリセット（各テストで独立した時刻を使用）
        ClockUtility::reset();

        // Sys Repository のキャッシュ（メンテナンス情報など）は CACHE_STORE に関わらず redis に入る。
        // 前のテストが載せた値を読まないよう、テスト専用のDB番号（phpunit.xml の REDIS_CACHE_DB）を空にする
        Cache::store('redis')->flush();

        // sign_up は初期スタミナをレベル 1 の mst_player_level から引き、無ければ失敗する。
        // サインアップを通すテストが多いので、既定でレベル 1 の行を用意する
        $this->ensureInitialLevelMaster();

        // 接続を閉じる前に消す（トランザクションで包むテストでは巻き戻しと同じ結果になる）
        $this->beforeApplicationDestroyed(fn () => $this->deleteSignedUpPlayers());
        $this->beforeApplicationDestroyed(fn () => $this->releaseDatabaseConnections());
    }

    /**
     * サインアップ時の初期付与で入った trx の行を消す
     *
     * 「新規プレイヤーには trx の行が無い」ことを前提に自前のフィクスチャを入れるテストが、
     * サインアップの後に呼んで初期データを外す。サインアップ後の状態を調べるテストは呼ばない。
     * 割り当てが無い（sys の行を先に消した）ときは何もしない。
     */
    protected function deleteInitialGrant(int $sysPlayerId): void
    {
        try {
            $connection = $this->playerConnection($sysPlayerId);
        } catch (\RuntimeException) {
            return;
        }

        foreach (['trx_diamond', 'trx_wallet', 'trx_wallet_balance', 'trx_item', 'trx_stamina'] as $table) {
            DB::connection($connection)->table($table)->where('sys_player_id', $sysPlayerId)->delete();
        }
    }

    /**
     * レベル 1 の mst_player_level（初期スタミナの元）が無ければ入れる
     *
     * 既にあれば（MstPlayerLevelTest などが自分で用意した値）触らない。
     * テストごとに行を消す／入れるテストは、自分で delete してから入れる。
     */
    protected function ensureInitialLevelMaster(): void
    {
        $mst = DB::connection('mst');

        if (! $mst->table('mst_player_level')->where('level', 1)->exists()) {
            $mst->table('mst_player_level')->insert([
                'level' => 1,
                'required_exp' => 0,
                'max_stamina' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        _BaseMstRepository::clearAllCaches();
    }

    /**
     * テストで使った接続のトランザクションを巻き戻し、接続を閉じる
     *
     * 失敗したテストが開いたままのトランザクションを残すと、PDOが解放されるまで
     * MySQLのメタデータロックを握り続ける。後続テストのTRUNCATEやDDLがその解放を
     * 無期限に待ち、テスト全体が止まる（CIで6時間止まった原因）。
     * テストの成否に関わらず、ここで確実に解放する。
     */
    private function releaseDatabaseConnections(): void
    {
        $database = $this->app['db'];

        // DBファサードをモックに差し替えたテストでは、実接続を持たない
        if ($database instanceof MockInterface) {
            return;
        }

        foreach ($database->getConnections() as $connection) {
            // 未接続（遅延接続のクロージャ）や切断済みの接続はトランザクションを持たない
            if (! $connection->getRawPdo() instanceof \PDO) {
                continue;
            }

            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            }

            $connection->disconnect();
        }
    }

    /**
     * signUpPlayer() で作ったプレイヤーの sys の行を消す
     *
     * UseCase を通すテストはトランザクションで包まないため、サインアップした行がDBに残る。
     * 残ると、IDや「割り当ての無いプレイヤー」の数に頼る他のテストが実行順によって落ちる。
     * trx の行はシャードとテストごとに違うため、各テストが自分で消す。
     */
    private function deleteSignedUpPlayers(): void
    {
        if ($this->signedUpPlayerIds === [] || $this->app['db'] instanceof MockInterface) {
            return;
        }

        // サインアップ時の初期付与で入った trx の行も消す（sys の割り当てを消す前に、接続先を引く）
        foreach ($this->signedUpPlayerIds as $playerId) {
            $this->deleteInitialGrant($playerId);
        }

        $sys = DB::connection('sys');

        foreach (['sys_player_device', 'sys_player_token', 'sys_sharding_node_player'] as $table) {
            $sys->table($table)->whereIn('sys_player_id', $this->signedUpPlayerIds)->delete();
        }
        $sys->table('sys_player')->whereIn('id', $this->signedUpPlayerIds)->delete();

        $this->signedUpPlayerIds = [];
    }

    /**
     * HTTPリクエストを送る前にscopedバインディングを捨てる
     *
     * Repositoryはリクエストスコープで共有され、取得したモデルをキャッシュする。
     * 本番はリクエストごとにコンテナが作り直されるため自然に破棄されるが、
     * テストは1つのアプリケーションを使い回すのでキャッシュがリクエストを跨いでしまう。
     * 実際の挙動に合わせるため、ここで明示的に捨てる。
     *
     * {@inheritDoc}
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Mstリポジトリのキャッシュをクリアする
     * テストでマスターデータを作成した後に呼び出すことで、
     * リポジトリが新しいデータを読み込むようにする
     */
    protected function refreshMstCache(): void
    {
        _BaseMstRepository::clearAllCaches();
    }

    /**
     * UnitOfWorkのキューをDBに反映する
     *
     * 本番ではUseCaseのトランザクション終了時にフラッシュされるため、
     * UseCaseを介さずService/Repositoryを直接検証するテストで、
     * DBの状態をアサートする前に呼び出す。
     */
    protected function flushQueue(): void
    {
        app()->make(QueryManagerInterface::class)->flush();
    }

    /**
     * サインアップして認証済みプレイヤーを用意する
     *
     * 認証情報はリクエスト入力ではなくアクセストークンから解決されるため、
     * エンドポイントを通しで叩くテストでは実際にサインアップする必要がある。
     * 作ったプレイヤーの sys の行はテストの終わりに消す。
     *
     * @return array{player: SysPlayer, token: string}
     */
    protected function signUpPlayer(?string $deviceId = null): array
    {
        $response = $this->postJson('/api/auth/sign_up', [
            'device_id' => $deviceId ?? 'test-device-'.uniqid(),
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ]);
        $response->assertOk();

        $player = SysPlayer::where('my_id', $response->json('sys_player.my_id'))->firstOrFail();
        $this->signedUpPlayerIds[] = $player->id;

        return [
            'player' => $player,
            'token' => $response->json('token.access_token'),
        ];
    }

    /**
     * アクセストークン付きのリクエストヘッダを組み立てる
     *
     * @return array<string, string>
     */
    /**
     * プレイヤーが割り当てられているTrxDB接続名を返す
     *
     * trx_* の行はプレイヤーごとにシャードへ分かれる。
     * テストで直接フィクスチャを差す場合は、必ずこの接続を使う。
     */
    protected function playerConnection(int $sysPlayerId): string
    {
        $nodeNo = DB::connection('sys')->table('sys_sharding_node_player as p')
            ->join('sys_sharding_node as n', 'n.id', '=', 'p.sys_sharding_node_id')
            ->where('p.sys_player_id', $sysPlayerId)
            ->value('n.node_no');

        if ($nodeNo === null) {
            throw new \RuntimeException("Shard assignment not found for player: {$sysPlayerId}");
        }

        return 'trx'.$nodeNo;
    }

    /**
     * プレイヤーが割り当てられているLogDB接続名を返す
     *
     * ログはtrxと同じシャードDBに同居している。対応は本番と同じShardMapperで求める
     */
    protected function playerLogConnection(int $sysPlayerId): string
    {
        return ShardMapper::resolveLogConnection($this->playerConnection($sysPlayerId));
    }

    protected function authHeaders(string $accessToken): array
    {
        return ['Authorization' => 'Bearer '.$accessToken];
    }

    /**
     * セッションのプレイヤーを差し替える（シャード割り当てもあわせて作る）
     *
     * trx_* / log_* の接続先は sys_sharding_node_player の割り当てで決まる。
     * 本番はサインアップがプレイヤー作成の直後に必ず作るため、割り当ての無い
     * ログイン中プレイヤーは存在しない。IDを決め打ちするテストでは同じ状態を
     * ここで用意する。
     *
     * 既定はノード1（trx1 / trx1）。テストが直接 connection('trx1') で
     * フィクスチャを差すため、そこへ揃える。
     */
    protected function useSessionPlayer(int $sysPlayerId, int $nodeNo = 1): void
    {
        $this->assignShard($sysPlayerId, $nodeNo);

        // 接続名はApiSessionのインスタンスに載る。プレイヤーを差し替えるだけでは
        // 前のプレイヤーのシャードを掴んだままになるため、いったん捨ててから入れ直す
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($sysPlayerId);
    }

    /**
     * そのテーブルに今は存在しないIDを返す
     *
     * 「存在しないID」を999のように決め打ちすると、採番がそこへ到達した時点で
     * 前提が崩れる。テストDBのAUTO_INCREMENTは行を消しても戻らないため、
     * 実行を重ねるほど決め打ちの値へ近づく（実際 sys_player は1200番台まで進んでいた）。
     * 現在の最大値の次であれば、その時点で必ず存在しない。
     */
    protected function nonExistentId(string $table, string $connection = 'sys', string $column = 'id'): int
    {
        return (int) DB::connection($connection)->table($table)->max($column) + 1;
    }

    /**
     * 存在しないプレイヤーIDを返す
     */
    protected function nonExistentSysPlayerId(): int
    {
        return $this->nonExistentId('sys_player');
    }

    /**
     * プレイヤーにシャードを割り当てる（既にあれば何もしない）
     */
    protected function assignShard(int $sysPlayerId, int $nodeNo = 1): void
    {
        $nodeId = DB::connection('sys')->table('sys_sharding_node')
            ->where('node_no', $nodeNo)
            ->value('id');

        if ($nodeId === null) {
            throw new \RuntimeException("Sharding node not found: node_no={$nodeNo}");
        }

        DB::connection('sys')->table('sys_sharding_node_player')->updateOrInsert(
            ['sys_player_id' => $sysPlayerId],
            [
                'sys_sharding_node_id' => $nodeId,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // シャード解決はRedisにも載る。割り当てを差し替えたら捨てて引き直させる
        Cache::forget("shard:player:{$sysPlayerId}");
    }
}
