<?php

namespace Tests\Feature\Providers;

use App\Repositories\Sys\SysPlayerTokenRepository;
use App\Repositories\Trx\TrxWalletRepository;
use NexusAuth\Services\TokenService;
use NexusVip\Services\VipLevelService;
use NexusVip\Services\VipPointService;
use NexusWallet\Services\WalletService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * コンテナに登録したサービスの寿命のテスト
 *
 * Trx/Log/Sys のRepositoryはプレイヤーごとの読み取りキャッシュを持つため
 * scoped で登録している（AppServiceProvider::registerScopedRepositories）。
 *
 * ところがそのRepositoryを受け取って持ち続けるサービスを singleton にすると、
 * サービス側がリクエストを跨いで生き残り、中のRepositoryごと持ち越してしまう。
 * Repositoryをscopedにした意味が消え、Octaneやキューワーカーで
 * 別プレイヤーの残高やトークンが見えることになる。
 *
 * FPMでは1リクエスト1プロセスのため表には出ないが、
 * 実行環境を変えた瞬間に事故になるのでここで固定する。
 */
class ServiceLifetimeTest extends TestCase
{
    /**
     * Trx/Log/Sys のRepositoryを保持するサービス
     *
     * @return list<array{0: class-string, 1: string}>
     */
    public static function scopedServices(): array
    {
        return [
            [WalletService::class, 'trx_wallet / trx_wallet_balance'],
            [VipPointService::class, 'sys_player / log_vip_point'],
            [TokenService::class, 'sys_player_token'],
        ];
    }

    #[Test]
    public function リクエスト内では同じインスタンスを返す(): void
    {
        foreach (self::scopedServices() as [$class, $_]) {
            $this->assertSame(
                $this->app->make($class),
                $this->app->make($class),
                "{$class} がリクエスト内で作り直されている"
            );
        }
    }

    #[Test]
    public function リクエストを跨ぐと作り直される(): void
    {
        foreach (self::scopedServices() as [$class, $tables]) {
            $before = $this->app->make($class);
            $this->app->forgetScopedInstances();

            $this->assertNotSame(
                $before,
                $this->app->make($class),
                "{$class} がリクエストを跨いで生き残っている（{$tables} のキャッシュを持ち越す）"
            );
        }
    }

    #[Test]
    public function 保持しているリポジトリも作り直される(): void
    {
        // サービスがscopedでも、中のRepositoryが古いままでは意味がない
        $wallet = $this->app->make(TrxWalletRepository::class);
        $token = $this->app->make(SysPlayerTokenRepository::class);

        $this->app->forgetScopedInstances();

        $this->assertNotSame($wallet, $this->app->make(TrxWalletRepository::class));
        $this->assertNotSame($token, $this->app->make(SysPlayerTokenRepository::class));
    }

    #[Test]
    public function マスターだけを見るサービスはsingletonでよい(): void
    {
        // Mstのリポジトリは静的にキャッシュする作りなので持ち越して困らない
        $before = $this->app->make(VipLevelService::class);
        $this->app->forgetScopedInstances();

        $this->assertSame($before, $this->app->make(VipLevelService::class));
    }
}
