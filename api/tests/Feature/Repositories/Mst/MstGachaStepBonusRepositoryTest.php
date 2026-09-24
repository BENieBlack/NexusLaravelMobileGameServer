<?php

namespace Tests\Feature\Repositories\Mst;

use App\Models\Mst\MstGachaStepBonus;
use App\Models\Mst\MstGachaStepBonusContent;
use App\Repositories\Mst\MstGachaStepBonusContentRepository;
use App\Repositories\Mst\MstGachaStepBonusRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ステップアップガチャのボーナスマスターのRepositoryのテスト
 *
 * ステップごとの確定枠と、その中身を引くところ。
 * 無効化した行を混ぜないことと、並び順が指定どおりであることが要点。
 * 並び順が崩れると、確定枠の見え方が配信ごとに変わる。
 *
 * どちらもServiceProviderに登録されているだけで現状呼び出し元が無いため、
 * 使うときに壊れていないよう挙動を固定しておく。
 */
class MstGachaStepBonusRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private MstGachaStepBonusRepository $bonusRepository;

    private MstGachaStepBonusContentRepository $contentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->bonusRepository = app(MstGachaStepBonusRepository::class);
        $this->contentRepository = app(MstGachaStepBonusContentRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    // ========================================
    // MstGachaStepBonusRepository
    // ========================================

    #[Test]
    public function ステップidで確定枠を引ける(): void
    {
        $this->makeBonus('bonus_1', stepId: 'step_1', position: 1);
        $this->makeBonus('bonus_other', stepId: 'step_2', position: 1);

        $bonuses = $this->bonusRepository->selectByStepId('step_1');

        $this->assertCount(1, $bonuses);
        $this->assertSame('bonus_1', $bonuses->first()->getAttribute('id'));
    }

    #[Test]
    public function 確定枠は位置の順に返る(): void
    {
        // 登録順ではなくpositionの順。崩れると確定枠の見え方が変わる
        $this->makeBonus('bonus_3', stepId: 'step_1', position: 3);
        $this->makeBonus('bonus_1', stepId: 'step_1', position: 1);
        $this->makeBonus('bonus_2', stepId: 'step_1', position: 2);

        $this->assertSame(
            ['bonus_1', 'bonus_2', 'bonus_3'],
            $this->bonusRepository->selectByStepId('step_1')
                ->map(fn (MstGachaStepBonus $bonus) => $bonus->getAttribute('id'))->all()
        );
    }

    #[Test]
    public function 無効な確定枠は外れる(): void
    {
        $this->makeBonus('bonus_on', stepId: 'step_1', position: 1);
        $this->makeBonus('bonus_off', stepId: 'step_1', position: 2, isActive: false);

        $bonuses = $this->bonusRepository->selectByStepId('step_1');

        $this->assertCount(1, $bonuses);
        $this->assertSame('bonus_on', $bonuses->first()->getAttribute('id'));
    }

    #[Test]
    public function 該当が無ければ空で返る(): void
    {
        $this->assertCount(0, $this->bonusRepository->selectByStepId('no_such_step'));
    }

    // ========================================
    // MstGachaStepBonusContentRepository
    // ========================================

    #[Test]
    public function 確定枠idで中身を引ける(): void
    {
        $this->makeContent('content_1', bonusId: 'bonus_1', sortOrder: 1);
        $this->makeContent('content_other', bonusId: 'bonus_2', sortOrder: 1);

        $contents = $this->contentRepository->selectByBonusId('bonus_1');

        $this->assertCount(1, $contents);
        $this->assertSame('content_1', $contents->first()->getAttribute('id'));
    }

    #[Test]
    public function 中身は並び順の指定どおりに返る(): void
    {
        $this->makeContent('content_c', bonusId: 'bonus_1', sortOrder: 3);
        $this->makeContent('content_a', bonusId: 'bonus_1', sortOrder: 1);
        $this->makeContent('content_b', bonusId: 'bonus_1', sortOrder: 2);

        $this->assertSame(
            ['content_a', 'content_b', 'content_c'],
            $this->contentRepository->selectListByBonusId('bonus_1')
                ->map(fn (MstGachaStepBonusContent $content) => $content->getAttribute('id'))->all()
        );
    }

    #[Test]
    public function 無効な中身は外れる(): void
    {
        $this->makeContent('content_on', bonusId: 'bonus_1', sortOrder: 1);
        $this->makeContent('content_off', bonusId: 'bonus_1', sortOrder: 2, isActive: false);

        $this->assertCount(1, $this->contentRepository->selectByBonusId('bonus_1'));
    }

    #[Test]
    public function 中身はidで直接引ける(): void
    {
        $this->makeContent('content_1', bonusId: 'bonus_1', sortOrder: 1, contentMstId: 'item_potion');

        $content = $this->contentRepository->selectById('content_1');

        $this->assertNotNull($content);
        $this->assertSame('item_potion', $content->getAttribute('content_mst_id'));
    }

    #[Test]
    public function 無効な中身もidでなら引ける(): void
    {
        // 単体取得は絞り込まない。配信を戻したときに参照できなくなると困る
        $this->makeContent('content_off', bonusId: 'bonus_1', sortOrder: 1, isActive: false);

        $this->assertNotNull($this->contentRepository->selectById('content_off'));
    }

    #[Test]
    public function 存在しないidはnullを返す(): void
    {
        $this->assertNull($this->contentRepository->selectById('no_such_content'));
    }

    #[Test]
    public function 中身が無ければ空で返る(): void
    {
        $this->assertCount(0, $this->contentRepository->selectByBonusId('no_such_bonus'));
    }

    private function makeBonus(
        string $id,
        string $stepId,
        int $position,
        bool $isActive = true,
    ): void {
        DB::connection('mst')->table('mst_gacha_step_bonus')->insert([
            'deploy_key' => 202601010,
            'id' => $id,
            'mst_gacha_step_id' => $stepId,
            'position' => $position,
            'bonus_count' => 1,
            'selection_type' => 'none',
            'is_pickup_only' => false,
            'is_active' => $isActive,
        ]);

        $this->refreshMstCache();
    }

    private function makeContent(
        string $id,
        string $bonusId,
        int $sortOrder,
        string $contentMstId = 'item_default',
        bool $isActive = true,
    ): void {
        DB::connection('mst')->table('mst_gacha_step_bonus_content')->insert([
            'deploy_key' => 202601010,
            'id' => $id,
            'mst_gacha_step_bonus_id' => $bonusId,
            'content_type' => 'item',
            'content_mst_id' => $contentMstId,
            'content_quantity' => 1,
            'amount' => 1,
            'weight' => 1,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        $this->refreshMstCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_gacha_step_bonus')->delete();
        DB::connection('mst')->table('mst_gacha_step_bonus_content')->delete();

        $this->refreshMstCache();
    }
}
