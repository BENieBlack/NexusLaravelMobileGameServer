<?php

namespace Tests\Feature\Account;

use App\Exceptions\GameErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\Utilities\ClockUtility;
use NexusAuth\Contracts\ExternalIdTokenVerifierInterface;
use NexusAuth\Exceptions\ExternalIdTokenException;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * データ引き継ぎ（機種変更）のエンドポイントのテスト
 *
 * 引き継いだ後は新端末だけが同じアカウントで遊べ、旧端末はサインインできなくなることを固定する。
 * 外部アカウントのIDトークン検証は差し替え、"valid:{sub}" だけを正しいトークンとして扱う。
 */
class AccountTransferEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const OLD_DEVICE = 'transfer-old-device';

    private const NEW_DEVICE = 'transfer-new-device';

    private const PASSWORD = 'secret-pass1';

    /** @var list<int> */
    private array $sysPlayerIds = [];

    private int $sysPlayerId;

    private string $token;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->app->instance(ExternalIdTokenVerifierInterface::class, new class implements ExternalIdTokenVerifierInterface
        {
            public function verify(string $provider, string $idToken): string
            {
                if ($idToken === 'unconfigured') {
                    throw ExternalIdTokenException::unsupportedProvider($provider);
                }

                if (! str_starts_with($idToken, 'valid:')) {
                    throw ExternalIdTokenException::invalidToken($provider, 'fake');
                }

                return substr($idToken, strlen('valid:'));
            }
        });

        ['player' => $player, 'token' => $token] = $this->signUpPlayer(self::OLD_DEVICE.'-'.uniqid());
        $this->sysPlayerId = $player->id;
        $this->token = $token;
        $this->sysPlayerIds[] = $player->id;
    }

    protected function tearDown(): void
    {
        // サインアップで作ったプレイヤーの sys の行は TestCase が消す
        foreach (['sys_player_transfer_code', 'sys_player_external_account'] as $table) {
            DB::connection('sys')->table($table)->whereIn('sys_player_id', $this->sysPlayerIds)->delete();
        }

        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 発行したコードとパスワードで別端末に引き継げる(): void
    {
        $oldDeviceId = $this->deviceIdOf($this->sysPlayerId);
        $transferCode = $this->issueTransferCode();

        $response = $this->postJson('/api/account/transfer_code/transfer', [
            'transfer_code' => $transferCode,
            'password' => self::PASSWORD,
            ...$this->newDevice(),
        ]);

        $response->assertOk();
        $this->assertSame($this->sysPlayerId, $this->sysPlayerIdOfMyId($response->json('sys_player.my_id')));

        // 新しいトークンで遊べる
        $this->getJson('/api/player/me', $this->authHeaders($response->json('token.access_token')))->assertOk();

        // 旧端末は外れているのでサインインできない
        $this->assertBusinessError($this->signIn($oldDeviceId), GameErrorCode::PLAYER_NOT_FOUND);

        // 使ったコードは2度使えない
        $this->assertBusinessError($this->postJson('/api/account/transfer_code/transfer', [
            'transfer_code' => $transferCode,
            'password' => self::PASSWORD,
            ...$this->newDevice(),
        ]), GameErrorCode::TRANSFER_CODE_INVALID);
    }

    #[Test]
    public function 発行すると有効期限が返る(): void
    {
        $response = $this->postJson(
            '/api/account/transfer_code/issue',
            ['password' => self::PASSWORD],
            $this->authHeaders($this->token),
        );

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{12}$/', $response->json('transfer_code'));
        $this->assertSame('2026-04-14 12:00:00', $response->json('expires_at'));
    }

    #[Test]
    public function 小文字で入力しても引き継げる(): void
    {
        $transferCode = $this->issueTransferCode();

        $this->postJson('/api/account/transfer_code/transfer', [
            'transfer_code' => strtolower($transferCode),
            'password' => self::PASSWORD,
            ...$this->newDevice(),
        ])->assertOk()->assertJsonPath('sys_player.my_id', $this->myIdOf($this->sysPlayerId));
    }

    #[Test]
    public function 発行し直すと前のコードは使えない(): void
    {
        $firstCode = $this->issueTransferCode();
        $secondCode = $this->issueTransferCode();

        $this->assertNotSame($firstCode, $secondCode);
        $this->assertBusinessError($this->transferByCode($firstCode, self::PASSWORD), GameErrorCode::TRANSFER_CODE_INVALID);
        $this->transferByCode($secondCode, self::PASSWORD)->assertOk();
    }

    #[Test]
    public function パスワードを続けて間違えるとロックされ時間が経てば解ける(): void
    {
        config([
            'account_transfer.transfer_code.max_failed_attempts' => 3,
            'account_transfer.transfer_code.lock_minutes' => 30,
        ]);
        $transferCode = $this->issueTransferCode();

        for ($i = 0; $i < 3; $i++) {
            $this->assertBusinessError($this->transferByCode($transferCode, 'wrong-pass'), GameErrorCode::TRANSFER_CODE_INVALID);
        }

        // 正しいパスワードでもロック中は通さない
        $this->assertBusinessError($this->transferByCode($transferCode, self::PASSWORD), GameErrorCode::TRANSFER_CODE_LOCKED);

        ClockUtility::setNow('2026-03-15 12:31:00');
        $this->transferByCode($transferCode, self::PASSWORD)->assertOk();
    }

    #[Test]
    public function パスワードの間違いは失敗しても記録される(): void
    {
        $transferCode = $this->issueTransferCode();

        $this->transferByCode($transferCode, 'wrong-pass');

        $failedCount = DB::connection('sys')->table('sys_player_transfer_code')
            ->where('sys_player_id', $this->sysPlayerId)->value('failed_count');
        $this->assertSame(1, (int) $failedCount);
    }

    #[Test]
    public function 期限切れのコードは使えない(): void
    {
        $transferCode = $this->issueTransferCode();

        ClockUtility::setNow('2026-04-14 12:00:01');

        $this->assertBusinessError($this->transferByCode($transferCode, self::PASSWORD), GameErrorCode::TRANSFER_CODE_EXPIRED);
    }

    #[Test]
    public function 引き継ぎ先の端末で遊んでいた別のプレイヤーからは端末が外れる(): void
    {
        $newDeviceId = self::NEW_DEVICE.'-'.uniqid();
        ['player' => $otherPlayer] = $this->signUpPlayer($newDeviceId);
        $this->sysPlayerIds[] = $otherPlayer->id;
        $transferCode = $this->issueTransferCode();

        $this->postJson('/api/account/transfer_code/transfer', [
            'transfer_code' => $transferCode,
            'password' => self::PASSWORD,
            ...$this->newDevice($newDeviceId),
        ])->assertOk();

        // 端末は引き継いだプレイヤーのものになり、元のプレイヤーのトークンは無効になる
        $this->assertSame($this->sysPlayerId, $this->sysPlayerIdOfDevice($newDeviceId));
        $this->assertSame(0, DB::connection('sys')->table('sys_player_token')
            ->where('sys_player_id', $otherPlayer->id)->whereNull('revoked_at')->count());
        $this->signIn($newDeviceId)->assertOk()->assertJsonPath('sys_player.my_id', $this->myIdOf($this->sysPlayerId));
    }

    #[Test]
    public function パスワードが短いと発行できない(): void
    {
        $this->postJson(
            '/api/account/transfer_code/issue',
            ['password' => 'short'],
            $this->authHeaders($this->token),
        )->assertStatus(422);
    }

    #[Test]
    public function 外部アカウントを紐付けて別端末で引き継げる(): void
    {
        $oldDeviceId = $this->deviceIdOf($this->sysPlayerId);

        $this->link('apple', 'valid:apple-user')
            ->assertOk()
            ->assertJsonPath('sys_player_external_account_list', [['provider' => 'apple']]);
        $this->getJson('/api/account/external/list', $this->authHeaders($this->token))
            ->assertOk()
            ->assertJsonPath('sys_player_external_account_list', [['provider' => 'apple']]);

        $response = $this->postJson('/api/account/external/transfer', [
            'provider' => 'apple',
            'id_token' => 'valid:apple-user',
            ...$this->newDevice(),
        ]);

        $response->assertOk();
        $this->assertSame($this->sysPlayerId, $this->sysPlayerIdOfMyId($response->json('sys_player.my_id')));
        $this->assertBusinessError($this->signIn($oldDeviceId), GameErrorCode::PLAYER_NOT_FOUND);

        // 紐付けは残るので、次の機種変更でも使える
        $this->postJson('/api/account/external/transfer', [
            'provider' => 'apple',
            'id_token' => 'valid:apple-user',
            ...$this->newDevice(self::NEW_DEVICE.'-next-'.uniqid()),
        ])->assertOk();
    }

    #[Test]
    public function 同じ外部アカウントは何度紐付けても1つだけ(): void
    {
        $this->link('google', 'valid:google-user')->assertOk();
        $this->link('google', 'valid:google-user')->assertOk();

        $this->assertSame(1, DB::connection('sys')->table('sys_player_external_account')
            ->where('sys_player_id', $this->sysPlayerId)->count());
    }

    #[Test]
    public function 同じ種類の別の外部アカウントは解除するまで紐付けられない(): void
    {
        $this->link('google', 'valid:google-user')->assertOk();

        $this->assertBusinessError($this->link('google', 'valid:google-other'), GameErrorCode::EXTERNAL_PROVIDER_ALREADY_LINKED);

        $this->postJson('/api/account/external/unlink', ['provider' => 'google'], $this->authHeaders($this->token))
            ->assertOk()
            ->assertJsonPath('sys_player_external_account_list', []);
        $this->link('google', 'valid:google-other')->assertOk();
    }

    #[Test]
    public function 別のプレイヤーに紐付いた外部アカウントは紐付けられない(): void
    {
        ['player' => $otherPlayer, 'token' => $otherToken] = $this->signUpPlayer();
        $this->sysPlayerIds[] = $otherPlayer->id;
        $this->link('apple', 'valid:apple-user', $otherToken)->assertOk();

        $this->assertBusinessError($this->link('apple', 'valid:apple-user'), GameErrorCode::EXTERNAL_ACCOUNT_LINKED_TO_OTHER_PLAYER);
    }

    #[Test]
    public function 紐付けていなければ解除も引き継ぎもできない(): void
    {
        $this->assertBusinessError(
            $this->postJson('/api/account/external/unlink', ['provider' => 'apple'], $this->authHeaders($this->token)),
            GameErrorCode::EXTERNAL_ACCOUNT_NOT_LINKED,
        );
        $this->assertBusinessError($this->postJson('/api/account/external/transfer', [
            'provider' => 'apple',
            'id_token' => 'valid:nobody',
            ...$this->newDevice(),
        ]), GameErrorCode::EXTERNAL_ACCOUNT_NOT_LINKED);
    }

    #[Test]
    public function 不正なトークンや未設定の種類はエラーになる(): void
    {
        $this->assertBusinessError($this->link('apple', 'forged'), GameErrorCode::EXTERNAL_ID_TOKEN_INVALID);
        $this->assertBusinessError($this->link('apple', 'unconfigured'), GameErrorCode::EXTERNAL_PROVIDER_UNAVAILABLE);
    }

    #[Test]
    public function 対応していない種類は受け付けない(): void
    {
        $this->link('line', 'valid:line-user')->assertStatus(422);
    }

    private function issueTransferCode(): string
    {
        $response = $this->postJson(
            '/api/account/transfer_code/issue',
            ['password' => self::PASSWORD],
            $this->authHeaders($this->token),
        );
        $response->assertOk();

        return $response->json('transfer_code');
    }

    private function transferByCode(string $transferCode, string $password): TestResponse
    {
        return $this->postJson('/api/account/transfer_code/transfer', [
            'transfer_code' => $transferCode,
            'password' => $password,
            ...$this->newDevice(),
        ]);
    }

    private function link(string $provider, string $idToken, ?string $token = null): TestResponse
    {
        return $this->postJson(
            '/api/account/external/link',
            ['provider' => $provider, 'id_token' => $idToken],
            $this->authHeaders($token ?? $this->token),
        );
    }

    private function signIn(string $deviceId): TestResponse
    {
        return $this->postJson('/api/auth/sign_in', $this->newDevice($deviceId));
    }

    /**
     * @return array{device_id: string, device_info: array<string, string>}
     */
    private function newDevice(?string $deviceId = null): array
    {
        return [
            'device_id' => $deviceId ?? self::NEW_DEVICE.'-'.uniqid(),
            'device_info' => [
                'os' => 'Android',
                'os_version' => '15',
                'model' => 'Pixel 9',
                'app_version' => '1.0.0',
            ],
        ];
    }

    private function assertBusinessError(TestResponse $response, int $errorCode): void
    {
        $response->assertStatus(299);
        $this->assertSame($errorCode, $response->json('error_code'));
    }

    private function deviceIdOf(int $sysPlayerId): string
    {
        return DB::connection('sys')->table('sys_player_device')->where('sys_player_id', $sysPlayerId)->value('uuid');
    }

    private function sysPlayerIdOfDevice(string $deviceId): int
    {
        return (int) DB::connection('sys')->table('sys_player_device')->where('uuid', $deviceId)->value('sys_player_id');
    }

    private function myIdOf(int $sysPlayerId): string
    {
        return DB::connection('sys')->table('sys_player')->where('id', $sysPlayerId)->value('my_id');
    }

    private function sysPlayerIdOfMyId(string $myId): int
    {
        return (int) DB::connection('sys')->table('sys_player')->where('my_id', $myId)->value('id');
    }
}
