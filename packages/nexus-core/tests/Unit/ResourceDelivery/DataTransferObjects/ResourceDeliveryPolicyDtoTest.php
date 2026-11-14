<?php

namespace Nexus\Core\Tests\Unit\ResourceDelivery\DataTransferObjects;

use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryPolicy;
use Nexus\Core\ResourceDelivery\Enums\ResourceDeliveryMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliveryPolicyのユニットテスト
 */
class ResourceDeliveryPolicyDtoTest extends TestCase
{
    /**
     * デフォルトポリシーを作成できる
     */
    #[Test]
    public function デフォルトポリシーを作成できる(): void
    {
        // Act
        $policy = ResourceDeliveryPolicy::createDefaultPolicy();

        // Assert
        $this->assertInstanceOf(ResourceDeliveryPolicy::class, $policy);
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType(ResourceType::PAID_DIAMOND)
        );
    }

    /**
     * 無償ダイヤ（item + free_diamond）は、アイテムの方針で解決される（設計 free-diamond-item Q20）
     *
     * ResourceType::DIAMOND の行を消しても、無償ダイヤは種別 item で配送されるので
     * 方針の解決は ITEM の行に従う。既定では NONE（メールへ回さない）、
     * 上限超過で例外を投げるポリシーでは ITEM と同じ THROW_ERROR。
     * 以前の DIAMOND の行（SEND_TO_MAILBOX）は、上限超過を検出する Handler が無く
     * 実行されたことが無いので、挙動は変わらない。
     */
    #[Test]
    public function 無償ダイヤはアイテムの方針で解決される(): void
    {
        $default = ResourceDeliveryPolicy::createDefaultPolicy();
        $this->assertSame(ResourceDeliveryMethod::NONE, $default->resolveMethodByResourceType(ResourceType::ITEM));

        $throwing = ResourceDeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy(new \Exception('limit'));
        $this->assertSame(
            ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            $throwing->resolveMethodByResourceType(ResourceType::ITEM)
        );
        // 無償ダイヤ用の独自の行は無い。廃止した diamond は未設定扱い（NONE）
        $this->assertSame(ResourceDeliveryMethod::NONE, $default->resolveMethodByResourceType('diamond'));
        $this->assertSame(ResourceDeliveryMethod::NONE, $throwing->resolveMethodByResourceType('diamond'));
    }

    /**
     * エラー時例外を投げるポリシーを作成できる
     */
    #[Test]
    public function エラー時例外を投げるポリシーを作成できる(): void
    {
        // Arrange
        $exception = new \Exception('Resource limit reached');

        // Act
        $policy = ResourceDeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            $policy->resolveMethodByResourceType(ResourceType::PAID_DIAMOND)
        );
        $this->assertSame(
            ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            $policy->resolveMethodByResourceType(ResourceType::GOLD)
        );
    }

    /**
     * リソースタイプで配送方法を取得できる
     */
    #[Test]
    public function リソースタイプで配送方法を取得できる(): void
    {
        // Arrange
        $policy = new ResourceDeliveryPolicy([
            ResourceType::PAID_DIAMOND->value => ResourceDeliveryMethod::SEND_TO_MAILBOX,
        ]);

        // Act
        $method = $policy->resolveMethodByResourceType(ResourceType::PAID_DIAMOND);

        // Assert
        $this->assertSame(ResourceDeliveryMethod::SEND_TO_MAILBOX, $method);
    }

    /**
     * 未設定のリソースタイプはNONEを返す
     */
    #[Test]
    public function 未設定のリソースタイプは_non_eを返す(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicy::createDefaultPolicy();

        // Act
        $method = $policy->resolveMethodByResourceType(ResourceType::GOLD);

        // Assert
        $this->assertSame(ResourceDeliveryMethod::NONE, $method);
    }

    /**
     * setMethodで配送方法を設定できる
     */
    #[Test]
    public function set_methodで配送方法を設定できる(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicy::createDefaultPolicy();

        // Act
        $policy->setMethod(ResourceType::GOLD, ResourceDeliveryMethod::SEND_TO_MAILBOX);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType(ResourceType::GOLD)
        );
    }

    /**
     * 例外が設定されていれば投げる
     */
    #[Test]
    public function 例外が設定されていれば投げる(): void
    {
        // Arrange
        $exception = new \Exception('Test exception');
        $policy = ResourceDeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // Act
        $policy->throwResourceLimitReachedExceptionIfSet();
    }

    /**
     * 例外が設定されていなければ何もしない
     */
    #[Test]
    public function 例外が設定されていなければ何もしない(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicy::createDefaultPolicy();

        // Act & Assert (no exception)
        $policy->throwResourceLimitReachedExceptionIfSet();
        $this->assertTrue(true); // No exception thrown
    }

    /**
     * エラー投げるリソースタイプを取得できる
     */
    #[Test]
    public function エラー投げるリソースタイプを取得できる(): void
    {
        // Arrange
        $exception = new \Exception('Test');
        $policy = ResourceDeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Act
        $types = $policy->findResourceTypesOfThrowErrorWhenResourceLimitReached([
            ResourceType::PAID_DIAMOND,
            ResourceType::GOLD,
        ]);

        // Assert
        $this->assertCount(2, $types);
        $this->assertContains(ResourceType::PAID_DIAMOND->value, $types);
        $this->assertContains(ResourceType::GOLD->value, $types);
    }

    /**
     * 文字列でリソースタイプを指定できる
     */
    #[Test]
    public function 文字列でリソースタイプを指定できる(): void
    {
        // Arrange
        $policy = new ResourceDeliveryPolicy([
            'paid_diamond' => ResourceDeliveryMethod::SEND_TO_MAILBOX,
        ]);

        // Act
        $method = $policy->resolveMethodByResourceType('paid_diamond');

        // Assert
        $this->assertSame(ResourceDeliveryMethod::SEND_TO_MAILBOX, $method);
    }

    /**
     * setMethodで文字列を使って設定できる
     */
    #[Test]
    public function set_methodで文字列を使って設定できる(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicy::createDefaultPolicy();

        // Act
        $policy->setMethod('custom_resource', ResourceDeliveryMethod::SEND_TO_MAILBOX);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType('custom_resource')
        );
    }
}
