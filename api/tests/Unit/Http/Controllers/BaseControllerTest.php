<?php

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\InfraErrorCode;
use App\Http\Controllers\_BaseController;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * _BaseControllerのテスト
 *
 * GameExceptionとその他の例外のHTTPステータスコードをテスト
 */
class BaseControllerTest extends TestCase
{
    private TestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestController;
    }

    #[Test]
    public function gameExceptionはHTTP299を返す(): void
    {
        // Arrange
        $exception = new GameException(
            GameErrorCode::STAMINA_NOT_ENOUGH,
            'Not enough stamina'
        );

        // Act
        $response = $this->controller->testHandleException($exception);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(299, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(GameErrorCode::STAMINA_NOT_ENOUGH, $content['error_code']);
        $this->assertEquals('Not enough stamina', $content['message']);
    }

    #[Test]
    public function その他の例外はHTTP500を返す(): void
    {
        // Arrange
        $exception = new \RuntimeException('Internal error', 0);

        // Act
        $response = $this->controller->testHandleException($exception);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(InfraErrorCode::UNKNOWN_ERROR, $content['error_code']);
    }

    #[Test]
    public function HTTPステータスコード範囲の例外は該当コードを返す(): void
    {
        // Arrange
        $exception = new \RuntimeException('Not found', 404);

        // Act
        $response = $this->controller->testHandleException($exception);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(404, $content['error_code']);
    }

    #[Test]
    public function executeメソッドでGameExceptionがキャッチされHTTP299を返す(): void
    {
        // Act
        $response = $this->controller->testExecuteWithGameException();

        // Assert
        $this->assertEquals(299, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(GameErrorCode::PLAYER_NOT_FOUND, $content['error_code']);
        $this->assertEquals('Player not found', $content['message']);
    }

    #[Test]
    public function executeメソッドで配列が返された場合はHTTP200を返す(): void
    {
        // Act
        $response = $this->controller->testExecuteWithArray();

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['success' => true, 'data' => 'test'], $content);
    }
}

/**
 * テスト用Controller
 *
 * handleExceptionをテストするための拡張クラス
 */
class TestController extends _BaseController
{
    /**
     * テスト用にhandleExceptionをpublicにする
     */
    public function testHandleException(\Throwable $e): JsonResponse
    {
        return $this->handleException($e);
    }

    /**
     * executeメソッドでGameExceptionをスローするテスト
     */
    public function testExecuteWithGameException(): JsonResponse
    {
        return $this->execute(function () {
            throw new GameException(
                GameErrorCode::PLAYER_NOT_FOUND,
                'Player not found'
            );
        });
    }

    /**
     * executeメソッドで配列を返すテスト
     */
    public function testExecuteWithArray(): JsonResponse
    {
        return $this->execute(function () {
            return ['success' => true, 'data' => 'test'];
        });
    }
}
