<?php

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\InfraErrorCode;
use App\Http\Controllers\_BaseController;
use App\Http\Responses\_BaseResponse;
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
    public function game_exceptionは_htt_p299を返す(): void
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
    public function その他の例外は_htt_p500を返す(): void
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
    public function htt_pステータスコード範囲の例外は該当コードを返す(): void
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
    public function executeメソッドで_game_exceptionがキャッチされ_htt_p299を返す(): void
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
    public function executeメソッドはレスポンスクラスをそのままjsonにする(): void
    {
        // Act
        $response = $this->controller->testExecuteWithResponse();

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['success' => true, 'value' => 'test'], $content);
    }

    #[Test]
    public function executeメソッドはレスポンスクラス以外を拒否する(): void
    {
        // Act: 配列を返すとLogicExceptionになり、500として扱われる
        $response = $this->controller->testExecuteWithArray();

        // Assert
        $this->assertEquals(500, $response->getStatusCode());
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
     * executeメソッドでレスポンスクラスを返すテスト
     */
    public function testExecuteWithResponse(): JsonResponse
    {
        return $this->execute(fn () => new TestResponse);
    }

    /**
     * executeメソッドでレスポンスクラス以外を返すテスト
     */
    public function testExecuteWithArray(): JsonResponse
    {
        return $this->execute(fn () => ['success' => true]);
    }
}

/**
 * テスト用レスポンス
 */
class TestResponse extends _BaseResponse
{
    public function toArray(): array
    {
        return ['success' => true, 'value' => 'test'];
    }
}
