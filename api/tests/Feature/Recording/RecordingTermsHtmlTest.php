<?php

namespace Tests\Feature\Recording;

use Database\Seeders\Recording\Scenarios\RecordingAgreementMultiSeeder;
use Database\Seeders\Recording\Scenarios\RecordingAgreementSeeder;
use Database\Seeders\Recording\Scenarios\RecordingResetSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * 規約の HTML（public/terms/）と、動画用シーダーが入れる sys_agreement.url の整合のテスト。
 *
 * HTTP の配信は nginx の仕事なので、ここではファイルの実在と中身だけを検査する
 * （CI にはホストやサービスを足さない）。運用は docs/terms_html.md。
 */
class RecordingTermsHtmlTest extends RecordingTestCase
{
    /** クライアントが描画できるタグ（docs/terms_html.md の一覧と同じ） */
    private const ALLOWED_TAGS = ['html', 'head', 'meta', 'title', 'body', 'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'br', 'hr'];

    #[Test]
    public function 動画用シーダーのurlはすべてpublic_termsの実在するファイルを指す(): void
    {
        // 前提: 規約を 5 件すべて入れる
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingAgreementMultiSeeder::class);

        // 操作
        $urls = DB::connection('sys')->table('sys_agreement')->where('version', 'like', 'rec-%')->pluck('url');

        // 期待: 相対パス（/terms/...）で、public_path() に実在する
        $this->assertCount(5, $urls);
        foreach ($urls as $url) {
            $this->assertMatchesRegularExpression('#^/terms/[A-Za-z0-9._-]+\.html$#', (string) $url);
            $this->assertFileExists(public_path((string) $url));
        }
    }

    #[Test]
    public function 古いurlの行も流し直すとurlが更新される(): void
    {
        // 前提: 以前のダミー URL のままの行がある
        DB::connection('sys')->table('sys_agreement')->insert([
            'version' => 'rec-required', 'title' => '[REC] old', 'url' => 'https://example.com/recording/terms',
            'is_required' => true, 'published_at' => '2020-01-01 00:00:00',
        ]);

        $this->runSeeder(RecordingAgreementSeeder::class);

        $this->assertSame(
            '/terms/terms-of-service-1.0.0.html',
            DB::connection('sys')->table('sys_agreement')->where('version', 'rec-required')->value('url'),
        );
        $this->assertSame(3, $this->countAgreements(markedOnly: true));
    }

    #[Test]
    public function 流し直しても増えずresetで消える(): void
    {
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingAgreementMultiSeeder::class);
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->assertSame(5, $this->countAgreements(markedOnly: true));

        $this->runSeeder(RecordingResetSeeder::class);

        $this->assertSame(0, $this->countAgreements(markedOnly: true));
    }

    #[Test]
    public function public_termsの_htm_lは許可タグだけで_ut_f8のmeta付きになっている(): void
    {
        $files = glob(public_path('terms/*.html'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $html = (string) file_get_contents($file);
            $name = basename($file);

            $this->assertTrue(mb_check_encoding($html, 'UTF-8'), "$name は UTF-8 であること");
            $this->assertStringContainsString('<meta charset="utf-8">', $html, "$name に meta charset が無い");
            $this->assertStringContainsString('これは開発・動作確認用のサンプルです', $html, "$name にサンプルの明記が無い");

            // 開きタグの名前を取り出し、許可タグ以外（script・style・img・table・div など）を弾く
            preg_match_all('/<\s*([A-Za-z][A-Za-z0-9]*)\b([^>]*)>/', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as [, $tag, $attributes]) {
                $this->assertContains(strtolower($tag), self::ALLOWED_TAGS, "$name に許可されないタグ <$tag> がある");
                // 属性は meta の charset・html の lang・a の href だけ
                preg_match_all('/([A-Za-z-]+)\s*=/', $attributes, $attrs);
                foreach ($attrs[1] as $attr) {
                    $this->assertContains(strtolower($attr), ['charset', 'lang', 'href'], "$name の <$tag> に許可されない属性 $attr がある");
                }
            }
        }
    }
}
