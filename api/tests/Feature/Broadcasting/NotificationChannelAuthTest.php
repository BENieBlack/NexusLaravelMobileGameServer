<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Sys\SysPlayer;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * private-notification.{myId} チャンネル認証のテスト
 *
 * チャンネル名はクライアントが知る公開 ID（my_id）。
 * 自分の my_id だけが通り、他人の my_id と内部の数値 ID は拒否されることを固定する。
 */
class NotificationChannelAuthTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** @var array{player: SysPlayer, token: string} */
    private array $me;

    /** @var array{player: SysPlayer, token: string} */
    private array $other;

    public function beginDatabaseTransaction(): void
    {
        // サインアップが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml は BROADCAST_CONNECTION=null で、null は認証を判定せず常に200を返す。
        // 認証の可否を確かめるため、reverb（Pusher 方式）に切り替えてチャンネル定義を登録し直す
        // （GuildChannelAuthTest と同じ手順）
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->me = $this->signUpPlayer();
        $this->other = $this->signUpPlayer();
    }

    #[Test]
    public function 自分のmy_idのチャンネルは認証が通る(): void
    {
        $this->authChannel('private-notification.'.$this->me['player']->my_id)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    #[Test]
    public function 他人のmy_idのチャンネルは拒否される(): void
    {
        $this->authChannel('private-notification.'.$this->other['player']->my_id)
            ->assertForbidden();
    }

    #[Test]
    public function 内部の数値idのチャンネルは拒否される(): void
    {
        $this->authChannel('private-notification.'.$this->me['player']->id)
            ->assertForbidden();
    }

    #[Test]
    public function 認証なしではチャンネル認証できない(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notification.'.$this->me['player']->my_id,
        ])->assertUnauthorized();
    }

    private function authChannel(string $channelName): TestResponse
    {
        return $this->withHeaders($this->authHeaders($this->me['token']))
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => $channelName,
            ]);
    }
}
