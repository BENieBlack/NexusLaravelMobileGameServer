<?php

namespace NexusResourceDelivery\Tests\Unit\DTOs;

use Nexus\Core\Support\CustomCollection;
use NexusResourceDelivery\DTOs\ResourceDeliveryCompleteDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliveryCompleteDtoのユニットテスト
 */
class ResourceDeliveryCompleteDtoTest extends TestCase
{
    /**
     * DTOを正常に作成できる
     */
    #[Test]
    public function dt_oを正常に作成できる(): void
    {
        // Arrange
        $contents = new CustomCollection;

        // Act
        $dto = new ResourceDeliveryCompleteDto($contents);

        // Assert
        $this->assertInstanceOf(ResourceDeliveryCompleteDto::class, $dto);
        $this->assertSame($contents, $dto->getContents());
    }

    /**
     * 空のコレクションで作成できる
     */
    #[Test]
    public function 空のコレクションで作成できる(): void
    {
        // Arrange
        $contents = new CustomCollection;

        // Act
        $dto = new ResourceDeliveryCompleteDto($contents);

        // Assert
        $this->assertInstanceOf(CustomCollection::class, $dto->getContents());
        $this->assertSame(0, $dto->getContents()->count());
    }

    /**
     * コンテンツを含むコレクションで作成できる
     */
    #[Test]
    public function コンテンツを含むコレクションで作成できる(): void
    {
        // Arrange
        $mockContent = $this->createMock(\stdClass::class);
        $contents = new CustomCollection([$mockContent]);

        // Act
        $dto = new ResourceDeliveryCompleteDto($contents);

        // Assert
        $this->assertSame(1, $dto->getContents()->count());
        $this->assertSame($mockContent, $dto->getContents()->first());
    }

    /**
     * 複数のコンテンツを含むコレクションで作成できる
     */
    #[Test]
    public function 複数のコンテンツを含むコレクションで作成できる(): void
    {
        // Arrange
        $mockContent1 = $this->createMock(\stdClass::class);
        $mockContent2 = $this->createMock(\stdClass::class);
        $mockContent3 = $this->createMock(\stdClass::class);
        $contents = new CustomCollection([$mockContent1, $mockContent2, $mockContent3]);

        // Act
        $dto = new ResourceDeliveryCompleteDto($contents);

        // Assert
        $this->assertSame(3, $dto->getContents()->count());
    }

    /**
     * getContentsで同じインスタンスを返す
     */
    #[Test]
    public function get_contentsで同じインスタンスを返す(): void
    {
        // Arrange
        $contents = new CustomCollection;
        $dto = new ResourceDeliveryCompleteDto($contents);

        // Act
        $result1 = $dto->getContents();
        $result2 = $dto->getContents();

        // Assert
        $this->assertSame($result1, $result2);
    }
}
