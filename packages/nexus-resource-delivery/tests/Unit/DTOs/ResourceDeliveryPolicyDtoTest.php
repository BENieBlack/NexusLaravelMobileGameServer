<?php

namespace NexusResourceDelivery\Tests\Unit\DTOs;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryPolicyDto;
use NexusResourceDelivery\Enums\ResourceDeliveryMethod;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliveryPolicyDtoのユニットテスト
 */
class ResourceDeliveryPolicyDtoTest extends TestCase
{
    /**
     * @test
     * デフォルトポリシーを作成できる
     */
    public function デフォルトポリシーを作成できる(): void
    {
        // Act
        $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();

        // Assert
        $this->assertInstanceOf(ResourceDeliveryPolicyDto::class, $policy);
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType(ResourceType::DIAMOND)
        );
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType(ResourceType::PAID_DIAMOND)
        );
    }

    /**
     * @test
     * エラー時例外を投げるポリシーを作成できる
     */
    public function エラー時例外を投げるポリシーを作成できる(): void
    {
        // Arrange
        $exception = new \Exception('Resource limit reached');

        // Act
        $policy = ResourceDeliveryPolicyDto::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            $policy->resolveMethodByResourceType(ResourceType::DIAMOND)
        );
        $this->assertSame(
            ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            $policy->resolveMethodByResourceType(ResourceType::GOLD)
        );
    }

    /**
     * @test
     * リソースタイプで配送方法を取得できる
     */
    public function リソースタイプで配送方法を取得できる(): void
    {
        // Arrange
        $policy = new ResourceDeliveryPolicyDto([
            ResourceType::DIAMOND->value => ResourceDeliveryMethod::SEND_TO_MAILBOX,
        ]);

        // Act
        $method = $policy->resolveMethodByResourceType(ResourceType::DIAMOND);

        // Assert
        $this->assertSame(ResourceDeliveryMethod::SEND_TO_MAILBOX, $method);
    }

    /**
     * @test
     * 未設定のリソースタイプはNONEを返す
     */
    public function 未設定のリソースタイプは_non_eを返す(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();

        // Act
        $method = $policy->resolveMethodByResourceType(ResourceType::GOLD);

        // Assert
        $this->assertSame(ResourceDeliveryMethod::NONE, $method);
    }

    /**
     * @test
     * setMethodで配送方法を設定できる
     */
    public function set_methodで配送方法を設定できる(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();

        // Act
        $policy->setMethod(ResourceType::GOLD, ResourceDeliveryMethod::SEND_TO_MAILBOX);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType(ResourceType::GOLD)
        );
    }

    /**
     * @test
     * 例外が設定されていれば投げる
     */
    public function 例外が設定されていれば投げる(): void
    {
        // Arrange
        $exception = new \Exception('Test exception');
        $policy = ResourceDeliveryPolicyDto::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // Act
        $policy->throwResourceLimitReachedExceptionIfSet();
    }

    /**
     * @test
     * 例外が設定されていなければ何もしない
     */
    public function 例外が設定されていなければ何もしない(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();

        // Act & Assert (no exception)
        $policy->throwResourceLimitReachedExceptionIfSet();
        $this->assertTrue(true); // No exception thrown
    }

    /**
     * @test
     * エラー投げるリソースタイプを取得できる
     */
    public function エラー投げるリソースタイプを取得できる(): void
    {
        // Arrange
        $exception = new \Exception('Test');
        $policy = ResourceDeliveryPolicyDto::createThrowErrorWhenResourceLimitReachedPolicy($exception);

        // Act
        $types = $policy->findResourceTypesOfThrowErrorWhenResourceLimitReached([
            ResourceType::DIAMOND,
            ResourceType::GOLD,
        ]);

        // Assert
        $this->assertCount(2, $types);
        $this->assertContains(ResourceType::DIAMOND->value, $types);
        $this->assertContains(ResourceType::GOLD->value, $types);
    }

    /**
     * @test
     * 文字列でリソースタイプを指定できる
     */
    public function 文字列でリソースタイプを指定できる(): void
    {
        // Arrange
        $policy = new ResourceDeliveryPolicyDto([
            'diamond' => ResourceDeliveryMethod::SEND_TO_MAILBOX,
        ]);

        // Act
        $method = $policy->resolveMethodByResourceType('diamond');

        // Assert
        $this->assertSame(ResourceDeliveryMethod::SEND_TO_MAILBOX, $method);
    }

    /**
     * @test
     * setMethodで文字列を使って設定できる
     */
    public function set_methodで文字列を使って設定できる(): void
    {
        // Arrange
        $policy = ResourceDeliveryPolicyDto::createDefaultPolicy();

        // Act
        $policy->setMethod('custom_resource', ResourceDeliveryMethod::SEND_TO_MAILBOX);

        // Assert
        $this->assertSame(
            ResourceDeliveryMethod::SEND_TO_MAILBOX,
            $policy->resolveMethodByResourceType('custom_resource')
        );
    }
}
