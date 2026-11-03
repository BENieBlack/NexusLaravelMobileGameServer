<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: レスポンス・リクエストのIDキー命名
 *
 * `*_id` はどのテーブルのどのカラムを指すか名前から分かるようにする。
 * `apply_id` のような曖昧な名前を検出する。
 * 配列を返すキーは `_list` で終わらせ、成否は HTTP ステータスで伝えるので `success` を返さない。
 */
class ResponseKeyNamingTest extends TestCase
{
    /**
     * テーブル接頭辞を持たないが、カラム名がそのまま対応するため許容するキー
     */
    private const ALLOWED = [
        'my_id',
        'sender_my_id',
        'receiver_my_id',
        'deleted_my_id',
        'device_id',
        'product_id',
        'transaction_id',
        'selected_candidate_id',
        // mst_mailbox.sender_id は sender_type で参照先が変わり、
        // 参照先が sys と mst にまたがるため _mst_id では表せない。
        // 送信者機能を作るときに型ごとの列へ分ける
        'sender_id',
        // sys_friend_apply の実カラム名（sender_ / receiver_ の接頭辞が付く）
        'sender_sys_player_id',
        'receiver_sys_player_id',
        // JWTのペイロードなどAPI契約以外で使う名前
        'authenticated_player_id',
        'unique_request_id',
    ];

    /**
     * 連想配列（キーで引くオブジェクト）として返すため、_list を付けないキー
     */
    private const OBJECT_KEYS = [
        // テーブル名 => ハッシュ・URL の対応表
        'tables',
    ];

    #[Test]
    public function test_id_keys_are_qualified_with_table_prefix(): void
    {
        $violations = [];

        foreach ($this->targetFiles() as $file) {
            $content = file_get_contents($file);

            if (! preg_match_all("/'([a-z][a-z0-9_]*_id)'/", $content, $matches)) {
                continue;
            }

            foreach (array_unique($matches[1]) as $key) {
                if (in_array($key, self::ALLOWED, true)) {
                    continue;
                }

                // sys_ / trx_ / mst_ / log_ で始まっていれば特定できる
                if (preg_match('/^(sys|trx|mst|log)_/', $key)) {
                    continue;
                }

                // 参照先が content_type などで決まる多相参照は、
                // 指す先が必ずマスターであることを _mst_id で示す
                // 例: content_mst_id, cost_mst_id, reward_mst_id
                if (str_ends_with($key, '_mst_id')) {
                    continue;
                }

                $violations[] = $this->relativePath($file).": '{$key}'";
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "IDキーはテーブルまで特定できる名前にしてください:\n".
            implode("\n", $violations)."\n".
            "例: 'apply_id' → 'sys_guild_apply_id'\n".
            "参照先が実行時に決まる場合は 'content_mst_id' のように _mst_id で終わらせてください\n".
            'カラム名に接頭辞が無いものは ALLOWED に追加してください。'
        );
    }

    #[Test]
    public function test_array_keys_end_with_list(): void
    {
        $violations = [];

        foreach ($this->targetFiles('Responses') as $file) {
            $content = file_get_contents($file);

            // 値が配列を作る式（array_map / array_values / コレクションの map・values・all、
            // または *Array / *List / *Ids という名前の変数）になっているキーを拾う
            $pattern = "/'([a-z][a-z0-9_]*)' => (?:array_map\\(|array_values\\(|\\$[a-zA-Z]*(?:Array|List|Ids)\\b|[^,\\n]*->(?:map|values|all)\\()/";
            if (! preg_match_all($pattern, $content, $matches)) {
                continue;
            }

            foreach (array_unique($matches[1]) as $key) {
                if (str_ends_with($key, '_list') || in_array($key, self::OBJECT_KEYS, true)) {
                    continue;
                }

                $violations[] = $this->relativePath($file).": '{$key}'";
            }
        }

        sort($violations);

        $this->assertSame(
            [],
            $violations,
            "配列を返すキーは _list で終わらせてください:\n".
            implode("\n", $violations)."\n".
            "例: 'prizes' → 'prize_list'、'mailbox_array' → 'mailbox_list'\n".
            'キーで引く連想配列として返す場合は OBJECT_KEYS に追加してください。'
        );
    }

    #[Test]
    public function test_responses_do_not_return_success_flag(): void
    {
        $violations = [];

        foreach ($this->targetFiles('Responses') as $file) {
            if (preg_match("/'(success|[a-z_]*_array)' =>/", file_get_contents($file), $m)) {
                $violations[] = $this->relativePath($file).": '{$m[1]}'";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "success は返さず、失敗は GameException（HTTP 299）で伝えてください。_array は _list にしてください:\n".
            implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function targetFiles(string ...$dirs): array
    {
        $files = [];

        foreach ($dirs === [] ? ['Responses', 'Requests'] : $dirs as $dir) {
            $base = realpath(__DIR__.'/../../app/Http/'.$dir);
            if ($base === false) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $entry) {
                /** @var \SplFileInfo $entry */
                if ($entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        $base = realpath(__DIR__.'/../../..');
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
