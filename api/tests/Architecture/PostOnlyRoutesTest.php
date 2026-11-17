<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: Controller が受けるエンドポイント（api/ 配下）は全て POST
 *
 * 読み取りに見えるエンドポイントも、期限切れの削除や日替わりのリセットなど内部で更新が入りうる。
 * GET は LockPlayerRequest と IdempotencyMiddleware の対象外なので、排他・冪等が効かない。
 * HTTP メソッドを実装の細部に結びつけないため、参照専用でも POST にする（.claude/api.md「ルーティング」）。
 *
 * 流れ:
 * 1. ルート一覧から `api/` 配下の GET / PUT / PATCH / DELETE を集める（HEAD は GET に付随するので無視する）
 * 1b. `api/` 以外（`/`・`up`・`broadcasting/auth`・`storage/{path}`）は Controller のエンドポイントではないので意図して対象外にする
 *     （api/ の外に Controller を足すとこのテストをすり抜けるので、足さないこと）
 * 2. 解消待ちの一覧（PENDING_ROUTES）に無いものがあれば失敗する（新しい GET を足さない）
 * 3. 一覧にあるのに GET でなくなったもの（移行済み）があれば、一覧から消すよう促して失敗する（一覧が縮む）
 *
 * 既存の GET は別の PR で POST へ移行する（Nexus の docs/backlog「GET→POST 統一」）。
 */
class PostOnlyRoutesTest extends TestCase
{
    /**
     * 解消待ちの GET ルート（URI の一覧）。新しい GET をここへ足してはならない。POST にしたら消す。
     *
     * @var list<string>
     */
    private const PENDING_ROUTES = [
        'api/account/external/list',
        'api/agreement/pending',
        'api/album/list',
        'api/auth/version',
        'api/chat/group/member/list',
        'api/chat/message/list',
        'api/chat/room/list',
        'api/friend/apply/list',
        'api/friend/list',
        'api/guild/apply/list',
        'api/guild/detail',
        'api/guild/list',
        'api/guild/member/list',
        'api/mailbox/list',
        'api/maintenance/status',
        'api/mission/list',
        'api/notification/list',
        'api/player/me',
        'api/reward_track/summary',
        'api/trade/list',
    ];

    private const NON_POST_METHODS = ['GET', 'PUT', 'PATCH', 'DELETE'];

    #[Test]
    public function test_api_routes_are_post_only_except_pending(): void
    {
        $nonPost = [];
        /** @var Route $route */
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            if (array_intersect(self::NON_POST_METHODS, $route->methods()) !== []) {
                $nonPost[] = $uri;
            }
        }
        $nonPost = array_values(array_unique($nonPost));

        $newViolations = array_values(array_diff($nonPost, self::PENDING_ROUTES));
        $this->assertSame(
            [],
            $newViolations,
            'Controller のエンドポイントは全て POST にしてください（PUT/PATCH/DELETE・GET は不可）: '.implode(', ', $newViolations)
        );

        $migrated = array_values(array_diff(self::PENDING_ROUTES, $nonPost));
        $this->assertSame(
            [],
            $migrated,
            'POST に移行済みです。PostOnlyRoutesTest::PENDING_ROUTES から消してください: '.implode(', ', $migrated)
        );
    }
}
