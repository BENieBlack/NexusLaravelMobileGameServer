<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * アーキテクチャテスト: 認証後のエンドポイントは全て POST。GET は認証不要の公開エンドポイントだけ
 *
 * 読み取りに見えるエンドポイントも、期限切れの削除や日替わりのリセットなど内部で更新が入りうる。
 * GET は LockPlayerRequest と IdempotencyMiddleware の対象外なので、排他・冪等が効かない。
 * HTTP メソッドを実装の細部に結びつけないため、認証後は参照専用でも POST にする（.claude/api.md「ルーティング」）。
 * 認証不要の公開 GET（メンテナンス状況・バージョン確認・ギルドの公開参照）だけは人の決定で GET のまま残す。
 *
 * 流れ:
 * 1. ルート一覧から `api/` 配下の GET / PUT / PATCH / DELETE を集める（HEAD は GET に付随するので無視する）
 * 1b. `api/` 以外（`/`・`up`・`broadcasting/auth`・`storage/{path}`）は Controller のエンドポイントではないので意図して対象外にする
 *     （api/ の外に Controller を足すとこのテストをすり抜けるので、足さないこと）
 * 2. 認証後（auth.token を通る）のルートに GET が無いこと
 * 3. 許可リスト（PUBLIC_GET_ROUTES）以外に GET / PUT / PATCH / DELETE が無いこと
 * 4. 許可リストの項目が GET でなくなったら落ちる（ラチェット。リストを現状に保つ）
 */
class PostOnlyRoutesTest extends TestCase
{
    /**
     * 認証不要の GET の許可リスト（人の決定。POST にしない）。新しい GET をここへ足してはならない。
     *
     * @var list<string>
     */
    private const PUBLIC_GET_ROUTES = [
        'api/auth/version',
        'api/guild/detail',
        'api/guild/list',
        'api/guild/member/list',
        'api/maintenance/status',
    ];

    private const NON_POST_METHODS = ['GET', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @return array<string, bool> URI => auth.token を通るか（GET 等の非 POST ルートのみ）
     */
    private function collectNonPostRoutes(): array
    {
        $nonPost = [];
        /** @var Route $route */
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            if (array_intersect(self::NON_POST_METHODS, $route->methods()) !== []) {
                $nonPost[$uri] = in_array('auth.token', $route->gatherMiddleware(), true);
            }
        }

        return $nonPost;
    }

    #[Test]
    public function test_authenticated_routes_have_no_get(): void
    {
        $authenticated = array_keys(array_filter($this->collectNonPostRoutes()));

        $this->assertSame(
            [],
            $authenticated,
            '認証後のエンドポイントは全て POST にしてください（GET・PUT・PATCH・DELETE は不可）: '.implode(', ', $authenticated)
        );
    }

    #[Test]
    public function test_no_non_post_routes_outside_public_allowlist(): void
    {
        $nonPost = array_keys($this->collectNonPostRoutes());

        $violations = array_values(array_diff($nonPost, self::PUBLIC_GET_ROUTES));
        $this->assertSame(
            [],
            $violations,
            'Controller のエンドポイントは POST にしてください（許可リスト以外の GET・PUT・PATCH・DELETE は不可）: '.implode(', ', $violations)
        );
    }

    #[Test]
    public function test_public_get_allowlist_is_current(): void
    {
        $nonPost = array_keys($this->collectNonPostRoutes());

        $stale = array_values(array_diff(self::PUBLIC_GET_ROUTES, $nonPost));
        $this->assertSame(
            [],
            $stale,
            'GET でなくなっています。PostOnlyRoutesTest::PUBLIC_GET_ROUTES から消してください: '.implode(', ', $stale)
        );
    }
}
