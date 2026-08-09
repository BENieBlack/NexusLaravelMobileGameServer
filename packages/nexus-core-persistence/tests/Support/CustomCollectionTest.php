<?php

namespace NexusPersistence\Tests\Support;

use Nexus\Core\Support\CustomCollection;
use PHPUnit\Framework\TestCase;

/**
 * CustomCollectionTest
 *
 * CustomCollectionのユニットテスト
 * パフォーマンス最適化されたメソッドが正しく動作することを検証
 */
class CustomCollectionTest extends TestCase
{
    public function test_filter_removes_null_values_by_default(): void
    {
        $collection = new CustomCollection([1, null, 3, null, 5]);
        $filtered = $collection->filter();

        $this->assertCount(3, $filtered);
        $this->assertEquals([0 => 1, 2 => 3, 4 => 5], $filtered->all());
    }

    public function test_filter_with_callback(): void
    {
        $collection = new CustomCollection([1, 2, 3, 4, 5]);
        $filtered = $collection->filter(fn($value) => $value > 3);

        $this->assertCount(2, $filtered);
        $this->assertTrue($filtered->contains(4));
        $this->assertTrue($filtered->contains(5));
    }

    public function test_where_with_equals_operator(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Alice'],
        ]);

        $filtered = $collection->where('name', 'Alice');

        $this->assertCount(2, $filtered);
    }

    public function test_where_with_comparison_operators(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'age' => 20],
            ['id' => 2, 'age' => 30],
            ['id' => 3, 'age' => 40],
        ]);

        $this->assertCount(2, $collection->where('age', '>', 25));
        $this->assertCount(2, $collection->where('age', '>=', 30));
        $this->assertCount(1, $collection->where('age', '<', 25));
        $this->assertCount(2, $collection->where('age', '<=', 30));
    }

    public function test_where_in(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'category' => 'A'],
            ['id' => 2, 'category' => 'B'],
            ['id' => 3, 'category' => 'C'],
            ['id' => 4, 'category' => 'A'],
        ]);

        $filtered = $collection->whereIn('category', ['A', 'C']);

        $this->assertCount(3, $filtered);
    }

    public function test_where_not_in(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'category' => 'A'],
            ['id' => 2, 'category' => 'B'],
            ['id' => 3, 'category' => 'C'],
        ]);

        $filtered = $collection->whereNotIn('category', ['B']);

        $this->assertCount(2, $filtered);
    }

    public function test_reject_with_callback(): void
    {
        $collection = new CustomCollection([1, 2, 3, 4, 5]);
        $rejected = $collection->reject(fn($value) => $value > 3);

        $this->assertCount(3, $rejected);
        $this->assertTrue($rejected->contains(1));
        $this->assertTrue($rejected->contains(2));
        $this->assertTrue($rejected->contains(3));
    }

    public function test_first_without_callback(): void
    {
        $collection = new CustomCollection([1, 2, 3, 4, 5]);
        
        $this->assertEquals(1, $collection->first());
    }

    public function test_first_with_callback(): void
    {
        $collection = new CustomCollection([1, 2, 3, 4, 5]);
        
        $this->assertEquals(4, $collection->first(fn($value) => $value > 3));
    }

    public function test_first_with_default(): void
    {
        $collection = new CustomCollection([]);
        
        $this->assertEquals('default', $collection->first(null, 'default'));
    }

    public function test_first_where(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ]);

        $result = $collection->firstWhere('name', 'Bob');

        $this->assertEquals(2, $result['id']);
    }

    public function test_first_where_with_operators(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'age' => 20],
            ['id' => 2, 'age' => 30],
            ['id' => 3, 'age' => 40],
        ]);

        $result = $collection->firstWhere('age', '>', 25);

        $this->assertEquals(2, $result['id']);
    }

    public function test_first_where_returns_null_when_not_found(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $result = $collection->firstWhere('name', 'Bob');

        $this->assertNull($result);
    }

    public function test_values_resets_keys(): void
    {
        $collection = new CustomCollection([
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);

        $values = $collection->values();

        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $values->all());
    }

    public function test_only_keeps_specified_keys(): void
    {
        $collection = new CustomCollection([
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
        ]);

        $only = $collection->only(['a', 'c']);

        $this->assertCount(2, $only);
        $this->assertEquals(['a' => 1, 'c' => 3], $only->all());
    }

    public function test_except_removes_specified_keys(): void
    {
        $collection = new CustomCollection([
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
        ]);

        $except = $collection->except(['b', 'd']);

        $this->assertCount(2, $except);
        $this->assertEquals(['a' => 1, 'c' => 3], $except->all());
    }

    public function test_sort_keys_ascending(): void
    {
        $collection = new CustomCollection([
            'c' => 3,
            'a' => 1,
            'b' => 2,
        ]);

        $sorted = $collection->sortKeys();

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $sorted->all());
    }

    public function test_sort_keys_descending(): void
    {
        $collection = new CustomCollection([
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);

        $sorted = $collection->sortKeys(SORT_REGULAR, true);

        $this->assertEquals(['c' => 3, 'b' => 2, 'a' => 1], $sorted->all());
    }

    public function test_sort_ascending(): void
    {
        $collection = new CustomCollection([3, 1, 2]);

        $sorted = $collection->sort();

        $this->assertEquals([1 => 1, 2 => 2, 0 => 3], $sorted->all());
    }

    public function test_sort_by_string_key(): void
    {
        $collection = new CustomCollection([
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $sorted = $collection->sortBy('id');

        $values = $sorted->values()->all();
        $this->assertEquals(1, $values[0]['id']);
        $this->assertEquals(2, $values[1]['id']);
        $this->assertEquals(3, $values[2]['id']);
    }

    public function test_sort_by_descending(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ]);

        $sorted = $collection->sortBy('id', SORT_REGULAR, true);

        $values = $sorted->values()->all();
        $this->assertEquals(3, $values[0]['id']);
        $this->assertEquals(2, $values[1]['id']);
        $this->assertEquals(1, $values[2]['id']);
    }

    public function test_chaining_multiple_methods(): void
    {
        $collection = new CustomCollection([
            ['id' => 1, 'category' => 'A', 'active' => true],
            ['id' => 2, 'category' => 'B', 'active' => false],
            ['id' => 3, 'category' => 'A', 'active' => true],
            ['id' => 4, 'category' => 'C', 'active' => true],
            ['id' => 5, 'category' => 'A', 'active' => false],
        ]);

        $result = $collection
            ->where('category', 'A')
            ->where('active', true)
            ->sortBy('id');

        $this->assertCount(2, $result);
        $values = $result->values()->all();
        $this->assertEquals(1, $values[0]['id']);
        $this->assertEquals(3, $values[1]['id']);
    }

    public function test_performance_comparison_with_large_dataset(): void
    {
        // 大量データでのパフォーマンステスト（実際の速度比較ではなく、動作確認）
        $items = [];
        for ($i = 0; $i < 10000; $i++) {
            $items[] = [
                'id' => $i,
                'category' => $i % 10,
                'active' => $i % 3 === 0, // 3で割り切れる場合にtrue
            ];
        }

        $collection = new CustomCollection($items);

        $startTime = microtime(true);
        $filtered = $collection
            ->where('category', 5)
            ->where('active', true)
            ->sortBy('id');
        $endTime = microtime(true);

        // category = 5 (5, 15, 25, 35, ..., 9995) = 1000件
        // そのうち3で割り切れる (15, 45, 75, ..., 9975) = 333件
        $this->assertCount(333, $filtered);
        
        // パフォーマンステストなので、合理的な時間内に完了することを確認
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(0.1, $executionTime, 'Execution should be fast');
    }
}
