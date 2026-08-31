<?php

namespace Tests\Feature\Repositories\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Repositories\Mst\LoginBonusRepositoryAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * LoginBonusRepositoryAdapter のテスト
 *
 * 復帰ボーナスの選定が要点。休眠日数の条件・配信期間・優先度で
 * どれを配るかが決まり、誤ると配ってはいけないものを配るか、
 * 配るべきものを配らない。
 *
 * 一度受け取った復帰ボーナスを有効期間内に再度配らないことも見る。
 */
class LoginBonusRepositoryAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private int $otherPlayerId;

    private LoginBonusRepositoryAdapter $repository;

    public function beginDatabaseTransaction(): void
    {
        // 履歴を素のクエリビルダで入れるため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        // 履歴はプレイヤーのシャードへ書くため、割り当て済みの実プレイヤーを使う
        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ['player' => $other] = $this->signUpPlayer();
        $this->otherPlayerId = $other->id;

        $this->repository = app(LoginBonusRepositoryAdapter::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ClockUtility::reset();

        parent::tearDown();
    }

    // ========================================
    // 通常ログインボーナス
    // ========================================

    #[Test]
    public function 有効な通常ボーナスのループ日数を取れる(): void
    {
        $this->makeDaily('daily_1', day: 1, loopDays: 7);

        $this->assertSame(7, $this->repository->selectLoopDaysForActiveBonus());
    }

    #[Test]
    public function 無効な通常ボーナスは見ない(): void
    {
        $this->makeDaily('daily_1', day: 1, loopDays: 7, isActive: false);

        $this->assertNull($this->repository->selectLoopDaysForActiveBonus());
        $this->assertNull($this->repository->selectActiveDailyBonus());
    }

    #[Test]
    public function 日数を指定して通常ボーナスを引ける(): void
    {
        $this->makeDaily('daily_1', day: 1);
        $this->makeDaily('daily_3', day: 3);

        $this->assertSame('daily_3', $this->repository->selectActiveByDay(3)['id']);
        $this->assertNull($this->repository->selectActiveByDay(5), '定義の無い日数はnull');
    }

    #[Test]
    public function 復帰ボーナスは通常ボーナスとして引かない(): void
    {
        // 種別を見ないと、復帰用の設定が日次として配られる
        $this->makeComeback('comeback_1', requiredAbsentDays: 7);

        $this->assertNull($this->repository->selectActiveDailyBonus());
        $this->assertNull($this->repository->selectLoopDaysForActiveBonus());
    }

    #[Test]
    public function 中身をソート順で取れる(): void
    {
        $this->makeDaily('daily_1', day: 1);
        $this->makeContent('daily_1', 'item_b', sortOrder: 2);
        $this->makeContent('daily_1', 'item_a', sortOrder: 1);

        $contents = $this->repository->selectContentsByLoginBonusId('daily_1');

        $this->assertSame(['item_a', 'item_b'], array_column($contents, 'content_mst_id'));
    }

    #[Test]
    public function 中身が無ければ空で返る(): void
    {
        $this->assertSame([], $this->repository->selectContentsByLoginBonusId('daily_1'));
    }

    // ========================================
    // 復帰ボーナスの選定
    // ========================================

    #[Test]
    public function 休眠日数の条件を満たすものだけ配る(): void
    {
        $this->makeComeback('comeback_7', requiredAbsentDays: 7);

        $this->assertNull($this->repository->selectEligibleComebackBonus(6), '足りなければ配らない');
        $this->assertSame('comeback_7', $this->repository->selectEligibleComebackBonus(7)['id'], '同値で配る');
        $this->assertSame('comeback_7', $this->repository->selectEligibleComebackBonus(30)['id']);
    }

    #[Test]
    public function 条件を満たすうち休眠日数が長い方を配る(): void
    {
        // 30日ぶりの復帰に7日用を配ると取り分が小さくなる
        $this->makeComeback('comeback_7', requiredAbsentDays: 7);
        $this->makeComeback('comeback_30', requiredAbsentDays: 30);

        $this->assertSame('comeback_30', $this->repository->selectEligibleComebackBonus(30)['id']);
        $this->assertSame('comeback_7', $this->repository->selectEligibleComebackBonus(10)['id']);
    }

    #[Test]
    public function 優先度は休眠日数より先に効く(): void
    {
        // 期間限定の復帰キャンペーンを通常の復帰ボーナスより優先する
        $this->makeComeback('comeback_30', requiredAbsentDays: 30, priority: 0);
        $this->makeComeback('campaign', requiredAbsentDays: 7, priority: 10);

        $this->assertSame('campaign', $this->repository->selectEligibleComebackBonus(30)['id']);
    }

    #[Test]
    public function 配信前のボーナスは配らない(): void
    {
        $this->makeComeback('comeback_7', requiredAbsentDays: 7, startAt: '2026-03-16 00:00:00');

        $this->assertNull($this->repository->selectEligibleComebackBonus(30));
    }

    #[Test]
    public function 配信終了後のボーナスは配らない(): void
    {
        $this->makeComeback('comeback_7', requiredAbsentDays: 7, endAt: '2026-03-15 11:59:59');

        $this->assertNull($this->repository->selectEligibleComebackBonus(30));
    }

    #[Test]
    public function 期間内なら配る(): void
    {
        $this->makeComeback(
            'comeback_7',
            requiredAbsentDays: 7,
            startAt: '2026-03-01 00:00:00',
            endAt: '2026-03-31 23:59:59'
        );

        $this->assertSame('comeback_7', $this->repository->selectEligibleComebackBonus(30)['id']);
    }

    #[Test]
    public function 期間の指定が無ければ常に配る(): void
    {
        $this->makeComeback('comeback_7', requiredAbsentDays: 7);

        $this->assertSame('comeback_7', $this->repository->selectEligibleComebackBonus(30)['id']);
    }

    #[Test]
    public function 無効な復帰ボーナスは配らない(): void
    {
        $this->makeComeback('comeback_7', requiredAbsentDays: 7, isActive: false);

        $this->assertNull($this->repository->selectEligibleComebackBonus(30));
    }

    // ========================================
    // 復帰ボーナスの重複防止
    // ========================================

    #[Test]
    public function 有効期間内に受け取っていれば再度配らない(): void
    {
        $this->makeHistory('comeback_7', receivedAt: '2026-03-10 12:00:00');

        $this->assertTrue($this->hasReceivedRecently('comeback_7', validDays: 30));
    }

    #[Test]
    public function 有効期間より前の受け取りは数えない(): void
    {
        // 30日の有効期間なら、31日前の受け取りはまた配る
        $this->makeHistory('comeback_7', receivedAt: '2026-02-12 12:00:00');

        $this->assertFalse($this->hasReceivedRecently('comeback_7', validDays: 30));
    }

    #[Test]
    public function 別のボーナスの履歴は数えない(): void
    {
        $this->makeHistory('comeback_30', receivedAt: '2026-03-10 12:00:00');

        $this->assertFalse($this->hasReceivedRecently('comeback_7', validDays: 30));
    }

    #[Test]
    public function 他人の履歴は数えない(): void
    {
        $this->makeHistory('comeback_7', receivedAt: '2026-03-10 12:00:00', sysPlayerId: $this->otherPlayerId);

        $this->assertFalse($this->hasReceivedRecently('comeback_7', validDays: 30));
    }

    #[Test]
    public function 履歴が無ければ配れる(): void
    {
        $this->assertFalse($this->hasReceivedRecently('comeback_7', validDays: 30));
    }

    private function hasReceivedRecently(string $bonusId, int $validDays): bool
    {
        return $this->repository->hasReceivedComebackBonusRecently(
            $this->sysPlayerId,
            $bonusId,
            $validDays,
            $this->playerConnection($this->sysPlayerId)
        );
    }

    private function makeDaily(
        string $id,
        int $day,
        int $loopDays = 7,
        bool $isActive = true,
    ): void {
        $this->insertBonus($id, MstLoginBonus::TYPE_DAILY, [
            'day' => $day,
            'loop_days' => $loopDays,
            'is_active' => $isActive,
        ]);
    }

    private function makeComeback(
        string $id,
        int $requiredAbsentDays,
        int $priority = 0,
        bool $isActive = true,
        ?string $startAt = null,
        ?string $endAt = null,
    ): void {
        $this->insertBonus($id, MstLoginBonus::TYPE_COMEBACK, [
            'day' => 1,
            'required_absent_days' => $requiredAbsentDays,
            'valid_days' => 30,
            'priority' => $priority,
            'is_active' => $isActive,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertBonus(string $id, string $type, array $attributes): void
    {
        DB::connection('mst')->table('mst_login_bonus')->insert(array_merge([
            'id' => $id,
            'type' => $type,
            'day' => 1,
            'loop_days' => 7,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->refreshMstCache();
    }

    private function makeContent(string $bonusId, string $contentMstId, int $sortOrder): void
    {
        DB::connection('mst')->table('mst_login_bonus_content')->insert([
            'mst_login_bonus_id' => $bonusId,
            'content_type' => 'item',
            'content_mst_id' => $contentMstId,
            'content_quantity' => 1,
            'amount' => 1,
            'is_paid' => false,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function makeHistory(string $bonusId, string $receivedAt, ?int $sysPlayerId = null): void
    {
        $sysPlayerId ??= $this->sysPlayerId;

        DB::connection($this->playerConnection($sysPlayerId))
            ->table('trx_login_bonus_history')->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_login_bonus_id' => $bonusId,
                'received_date' => $receivedAt,
                'reward_type' => 'item',
                'reward_mst_id' => 'item_potion',
                'reward_amount' => 1,
                'is_paid' => false,
                'created_at' => now(),
            ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();
        foreach ([$this->sysPlayerId, $this->otherPlayerId] as $playerId) {
            DB::connection($this->playerConnection($playerId))
                ->table('trx_login_bonus_history')->where('sys_player_id', $playerId)->delete();
        }
        $this->refreshMstCache();
    }
}
