<?php

namespace Nexus\Core\Tests\Unit\Support;

use Illuminate\Support\Collection;
use Nexus\Core\Support\CustomCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CustomCollection のユニットテスト
 *
 * Laravelの Collection を、インスタンスコピーを避けるために
 * array_filter 等で書き直したクラス。
 *
 * 速さのための上書きなので、いちばんの危険は「本家と挙動がずれること」。
 * 各メソッドは同じ入力に対して Collection と同じ結果になることを確かめる。
 */
class CustomCollectionTest extends TestCase
{
    /**
     * @var list<array{id: int, name: string, level: int|null}>
     */
    private const ROWS = [
        ['id' => 1, 'name' => 'a', 'level' => 10],
        ['id' => 2, 'name' => 'b', 'level' => 20],
        ['id' => 3, 'name' => 'c', 'level' => 10],
        ['id' => 4, 'name' => 'd', 'level' => null],
    ];

    #[Test]
    public function filterは本家と同じ結果になる(): void
    {
        $callback = fn (array $row) => $row['level'] !== null && $row['level'] >= 20;

        $this->assertMatchesCollection(
            fn (CustomCollection|Collection $c) => $c->filter($callback)
        );
    }

    #[Test]
    public function filterはコールバック省略でnullだけを落とす(): void
    {
        // ここは本家と挙動が違う。
        // 本家は falsy を全て落とすが、こちらは 0 や '' を残す（意図的）
        $custom = new CustomCollection([1, 0, null, '', 'x', false]);

        $this->assertSame([1, 0, '', 'x', false], array_values($custom->filter()->all()));
    }

    #[Test]
    public function filterはキーを保つ(): void
    {
        $custom = new CustomCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['b' => 2, 'c' => 3], $custom->filter(fn (int $v) => $v >= 2)->all());
    }

    #[Test]
    public function whereは本家と同じ結果になる(): void
    {
        $this->assertMatchesCollection(fn ($c) => $c->where('level', 10));
    }

    #[Test]
    public function whereは演算子を解釈する(): void
    {
        foreach (['=', '==', '===', '!=', '!==', '<', '>', '<=', '>='] as $operator) {
            $this->assertMatchesCollection(
                fn ($c) => $c->where('level', $operator, 10),
                "演算子 {$operator} の結果がずれている"
            );
        }
    }

    #[Test]
    public function where_inと_where_not_inは本家と同じ結果になる(): void
    {
        $this->assertMatchesCollection(fn ($c) => $c->whereIn('level', [10, 20]));
        $this->assertMatchesCollection(fn ($c) => $c->whereNotIn('level', [10]));
    }

    #[Test]
    public function rejectは本家と同じ結果になる(): void
    {
        $this->assertMatchesCollection(fn ($c) => $c->reject(fn (array $row) => $row['level'] === 10));
    }

    #[Test]
    public function rejectはcallableでなければ本家に任せる(): void
    {
        $custom = new CustomCollection([1, 2, 3]);

        // 値そのものを渡す使い方は親クラスの実装が担当する
        $this->assertSame([1, 3], array_values($custom->reject(2)->all()));
    }

    #[Test]
    public function firstは条件に合う最初の要素を返す(): void
    {
        $custom = new CustomCollection(self::ROWS);

        $this->assertSame(self::ROWS[1], $custom->first(fn (array $row) => $row['level'] === 20));
        $this->assertSame(self::ROWS[0], $custom->first());
    }

    #[Test]
    public function firstは見つからなければ既定値を返す(): void
    {
        $custom = new CustomCollection(self::ROWS);

        $this->assertNull($custom->first(fn (array $row) => $row['level'] === 999));
        $this->assertSame('none', $custom->first(fn (array $row) => $row['level'] === 999, 'none'));
        $this->assertSame('none', (new CustomCollection)->first(null, 'none'), '空でも既定値を返す');
    }

    #[Test]
    public function first_whereは本家と同じ結果になる(): void
    {
        $custom = new CustomCollection(self::ROWS);
        $plain = new Collection(self::ROWS);

        $this->assertSame($plain->firstWhere('level', 10), $custom->firstWhere('level', 10));
        $this->assertSame($plain->firstWhere('level', '>', 10), $custom->firstWhere('level', '>', 10));
        $this->assertSame($plain->firstWhere('level', 999), $custom->firstWhere('level', 999));
    }

    #[Test]
    public function valuesはキーを振り直す(): void
    {
        $custom = new CustomCollection([5 => 'a', 9 => 'b']);

        $this->assertSame(['a', 'b'], $custom->values()->all());
    }

    #[Test]
    public function onlyとexceptは本家と同じ結果になる(): void
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(
            (new Collection($items))->only(['a', 'c'])->all(),
            (new CustomCollection($items))->only(['a', 'c'])->all(),
        );
        $this->assertSame(
            (new Collection($items))->except(['a', 'c'])->all(),
            (new CustomCollection($items))->except(['a', 'c'])->all(),
        );
    }

    #[Test]
    public function onlyとexceptはnullで全件を返す(): void
    {
        $items = ['a' => 1, 'b' => 2];

        $this->assertSame($items, (new CustomCollection($items))->only(null)->all());
        $this->assertSame($items, (new CustomCollection($items))->except(null)->all());
    }

    #[Test]
    public function sort_keysはキー順に並べる(): void
    {
        $items = ['c' => 3, 'a' => 1, 'b' => 2];

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], (new CustomCollection($items))->sortKeys()->all());
        $this->assertSame(['c' => 3, 'b' => 2, 'a' => 1], (new CustomCollection($items))->sortKeys(SORT_REGULAR, true)->all());
    }

    #[Test]
    public function sortは値順に並べる(): void
    {
        $custom = new CustomCollection([3, 1, 2]);

        $this->assertSame([1, 2, 3], array_values($custom->sort()->all()));
        $this->assertSame([3, 2, 1], array_values($custom->sort(SORT_REGULAR, true)->all()));
    }

    #[Test]
    public function sort_byは本家と同じ並びになる(): void
    {
        $custom = (new CustomCollection(self::ROWS))->sortBy('name');
        $plain = (new Collection(self::ROWS))->sortBy('name');

        $this->assertSame($plain->all(), $custom->all());
        $this->assertSame(
            (new Collection(self::ROWS))->sortByDesc('name')->all(),
            (new CustomCollection(self::ROWS))->sortBy('name', SORT_REGULAR, true)->all(),
        );
    }

    #[Test]
    public function 加工してもcustom_collectionのまま返る(): void
    {
        // 戻り値が本家のCollectionに変わると、呼び出し側の型が崩れる
        $custom = new CustomCollection(self::ROWS);

        foreach ([
            $custom->filter(fn () => true),
            $custom->where('level', 10),
            $custom->whereIn('level', [10]),
            $custom->whereNotIn('level', [10]),
            $custom->reject(fn () => false),
            $custom->values(),
            $custom->only([0]),
            $custom->except([0]),
            $custom->sortKeys(),
            $custom->sort(),
            $custom->sortBy('name'),
        ] as $result) {
            $this->assertInstanceOf(CustomCollection::class, $result);
        }
    }

    #[Test]
    public function 空のコレクションでも壊れない(): void
    {
        $custom = new CustomCollection;

        $this->assertSame([], $custom->filter(fn () => true)->all());
        $this->assertSame([], $custom->where('level', 10)->all());
        $this->assertSame([], $custom->values()->all());
        $this->assertSame([], $custom->sortBy('name')->all());
        $this->assertNull($custom->firstWhere('level', 10));
    }

    /**
     * 同じ操作を本家のCollectionにも適用し、結果が一致することを確かめる
     *
     * @param  \Closure(CustomCollection|Collection): (CustomCollection|Collection)  $operation
     */
    private function assertMatchesCollection(\Closure $operation, string $message = ''): void
    {
        $this->assertSame(
            $operation(new Collection(self::ROWS))->all(),
            $operation(new CustomCollection(self::ROWS))->all(),
            $message,
        );
    }
}
