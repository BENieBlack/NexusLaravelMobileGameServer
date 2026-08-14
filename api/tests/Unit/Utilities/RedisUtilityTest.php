<?php

namespace Tests\Unit\Utilities;

use Nexus\Core\Utilities\RedisUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RedisUtilityのユニットテスト
 */
class RedisUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト前にRedisをクリア
        RedisUtility::flush();
    }

    protected function tearDown(): void
    {
        // テスト後にRedisをクリア
        RedisUtility::flush();

        parent::tearDown();
    }

    /**
     * 基本的なput/getが動作する
     */
    #[Test]
    public function test_基本的なput_getが動作する()
    {
        // Put
        $result = RedisUtility::put('test_key', 'test_value', 60);
        $this->assertTrue($result);

        // Get
        $value = RedisUtility::get('test_key');
        $this->assertEquals('test_value', $value);
    }

    /**
     * 配列データの保存と取得ができる
     */
    #[Test]
    public function test_配列データの保存と取得ができる()
    {
        $data = [
            'name' => 'テストユーザー',
            'level' => 50,
            'items' => [1, 2, 3],
        ];

        RedisUtility::put('user_data', $data, 60);
        $retrieved = RedisUtility::get('user_data');

        $this->assertEquals($data, $retrieved);
    }

    /**
     * hasメソッドが正しく動作する
     */
    #[Test]
    public function test_hasメソッドが正しく動作する()
    {
        // キーが存在しない
        $this->assertFalse(RedisUtility::has('non_existent_key'));

        // キーを保存
        RedisUtility::put('exists_key', 'value', 60);

        // キーが存在する
        $this->assertTrue(RedisUtility::has('exists_key'));
    }

    /**
     * forgetメソッドが正しく動作する
     */
    #[Test]
    public function test_forgetメソッドが正しく動作する()
    {
        // キーを保存
        RedisUtility::put('delete_me', 'value', 60);
        $this->assertTrue(RedisUtility::has('delete_me'));

        // キーを削除
        $result = RedisUtility::forget('delete_me');
        $this->assertTrue($result);
        $this->assertFalse(RedisUtility::has('delete_me'));
    }

    /**
     * foreverメソッドが永続保存する
     */
    #[Test]
    public function test_foreverメソッドが永続保存する()
    {
        RedisUtility::forever('permanent_key', 'permanent_value');

        $value = RedisUtility::get('permanent_key');
        $this->assertEquals('permanent_value', $value);

        // Note: TTLの確認はLaravelのキャッシュプレフィックスの影響でスキップ
    }

    /**
     * increment_decrementが動作する
     */
    #[Test]
    public function test_increment_decrementが動作する()
    {
        // 初期値
        RedisUtility::put('counter', 10, 60);

        // Increment
        $result = RedisUtility::increment('counter');
        $this->assertEquals(11, $result);
        $this->assertEquals(11, RedisUtility::get('counter'));

        // Increment by 5
        $result = RedisUtility::increment('counter', 5);
        $this->assertEquals(16, $result);

        // Decrement
        $result = RedisUtility::decrement('counter');
        $this->assertEquals(15, $result);

        // Decrement by 3
        $result = RedisUtility::decrement('counter', 3);
        $this->assertEquals(12, $result);
    }

    /**
     * rememberメソッドがキャッシュを使用する
     */
    #[Test]
    public function test_rememberメソッドがキャッシュを使用する()
    {
        $callCount = 0;

        // 初回はクロージャが実行される
        $result = RedisUtility::remember('remember_key', 60, function () use (&$callCount) {
            $callCount++;

            return 'computed_value';
        });

        $this->assertEquals('computed_value', $result);
        $this->assertEquals(1, $callCount);

        // 2回目はキャッシュから取得
        $result = RedisUtility::remember('remember_key', 60, function () use (&$callCount) {
            $callCount++;

            return 'computed_value';
        });

        $this->assertEquals('computed_value', $result);
        $this->assertEquals(1, $callCount); // クロージャは実行されない
    }

    /**
     * pullメソッドが取得後に削除する
     */
    #[Test]
    public function test_pullメソッドが取得後に削除する()
    {
        RedisUtility::put('pull_key', 'pull_value', 60);

        // Pull: 取得して削除
        $value = RedisUtility::pull('pull_key');
        $this->assertEquals('pull_value', $value);

        // キーは削除されている
        $this->assertFalse(RedisUtility::has('pull_key'));
    }

    /**
     * putCompressedとfetchCompressedが動作する
     */
    #[Test]
    public function test_put_compressedとget_compressedが動作する()
    {
        $largeData = [
            'items' => array_fill(0, 100, [
                'id' => rand(1, 1000),
                'name' => 'Item_'.rand(1, 100),
                'description' => 'This is a test item with a longer description',
            ]),
        ];

        // 圧縮して保存
        $result = RedisUtility::putCompressed('large_data', $largeData, 60);
        $this->assertTrue($result);

        // 解凍して取得
        $retrieved = RedisUtility::fetchCompressed('large_data');
        $this->assertEquals($largeData, $retrieved);
    }

    /**
     * rememberCompressedがキャッシュを使用する
     */
    #[Test]
    public function test_remember_compressedがキャッシュを使用する()
    {
        $callCount = 0;
        $data = ['large' => 'data'];

        // 初回はクロージャが実行される
        $result = RedisUtility::rememberCompressed('compressed_remember', 60, function () use (&$callCount, $data) {
            $callCount++;

            return $data;
        });

        $this->assertEquals($data, $result);
        $this->assertEquals(1, $callCount);

        // 2回目はキャッシュから取得
        $result = RedisUtility::rememberCompressed('compressed_remember', 60, function () use (&$callCount, $data) {
            $callCount++;

            return $data;
        });

        $this->assertEquals($data, $result);
        $this->assertEquals(1, $callCount); // クロージャは実行されない
    }

    /**
     * prefixKeyがプレフィックスを付与する
     */
    #[Test]
    public function test_prefix_keyがプレフィックスを付与する()
    {
        $key = RedisUtility::prefixKey('user', '123');
        $this->assertEquals('user:123', $key);

        $key = RedisUtility::prefixKey('session', 'abc123');
        $this->assertEquals('session:abc123', $key);
    }

    /**
     * addメソッドが既存キーには上書きしない
     */
    #[Test]
    public function test_addメソッドが既存キーには上書きしない()
    {
        // 初回は成功
        $result = RedisUtility::add('add_key', 'first_value', 60);
        $this->assertTrue($result);
        $this->assertEquals('first_value', RedisUtility::get('add_key'));

        // 既存キーには上書きされない
        $result = RedisUtility::add('add_key', 'second_value', 60);
        $this->assertFalse($result);
        $this->assertEquals('first_value', RedisUtility::get('add_key'));
    }

    /**
     * flushとclearが全てのキャッシュをクリアする
     */
    #[Test]
    public function test_flushとclearが全てのキャッシュをクリアする()
    {
        // 複数のキーを保存
        RedisUtility::put('key1', 'value1', 60);
        RedisUtility::put('key2', 'value2', 60);
        RedisUtility::put('key3', 'value3', 60);

        $this->assertTrue(RedisUtility::has('key1'));
        $this->assertTrue(RedisUtility::has('key2'));
        $this->assertTrue(RedisUtility::has('key3'));

        // Flush
        RedisUtility::flush();

        $this->assertFalse(RedisUtility::has('key1'));
        $this->assertFalse(RedisUtility::has('key2'));
        $this->assertFalse(RedisUtility::has('key3'));

        // clearでも同じ
        RedisUtility::put('key4', 'value4', 60);
        $this->assertTrue(RedisUtility::has('key4'));

        RedisUtility::clear();
        $this->assertFalse(RedisUtility::has('key4'));
    }

    /**
     * deleteManyが複数のキーを削除する
     */
    #[Test]
    public function test_delete_manyが複数のキーを削除する()
    {
        // 複数のキーを保存
        RedisUtility::put('delete1', 'value1', 60);
        RedisUtility::put('delete2', 'value2', 60);
        RedisUtility::put('delete3', 'value3', 60);
        RedisUtility::put('keep', 'keep_value', 60);

        // 複数削除
        $result = RedisUtility::deleteMany(['delete1', 'delete2', 'delete3']);
        $this->assertTrue($result);

        $this->assertFalse(RedisUtility::has('delete1'));
        $this->assertFalse(RedisUtility::has('delete2'));
        $this->assertFalse(RedisUtility::has('delete3'));
        $this->assertTrue(RedisUtility::has('keep')); // これは残る
    }

    /**
     * 存在しないキーのgetはデフォルト値を返す
     */
    #[Test]
    public function test_存在しないキーのgetはデフォルト値を返す()
    {
        $value = RedisUtility::get('non_existent', 'default_value');
        $this->assertEquals('default_value', $value);

        $value = RedisUtility::get('non_existent');
        $this->assertNull($value);
    }

    /**
     * 存在しないキーのfetchCompressedはデフォルト値を返す
     */
    #[Test]
    public function test_存在しないキーのget_compressedはデフォルト値を返す()
    {
        $value = RedisUtility::fetchCompressed('non_existent', ['default' => 'data']);
        $this->assertEquals(['default' => 'data'], $value);

        $value = RedisUtility::fetchCompressed('non_existent');
        $this->assertNull($value);
    }
}
