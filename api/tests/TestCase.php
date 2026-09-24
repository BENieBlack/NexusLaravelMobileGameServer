<?php

namespace Tests;

use App\Models\Sys\SysPlayer;
use App\Persistence\ApiSession;
use App\Repositories\Mst\_BaseMstRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusSecurity\Middleware\VerifyClientSignature;
use NexusUnitOfWork\Contracts\QueryManagerInterface;
use Tests\Support\InMemoryMaintenanceStorage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト環境ではクライアント署名検証を無効化
        $this->withoutMiddleware(VerifyClientSignature::class);

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

        return [
            'player' => SysPlayer::where('my_id', $response->json('sys_player.my_id'))->firstOrFail(),
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
     */
    protected function playerLogConnection(int $sysPlayerId): string
    {
        return 'log'.substr($this->playerConnection($sysPlayerId), 3);
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
     * 既定はノード1（trx1 / log1）。テストが直接 connection('trx1') で
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
