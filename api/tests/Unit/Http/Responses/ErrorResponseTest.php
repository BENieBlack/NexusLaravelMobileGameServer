<?php

namespace Tests\Unit\Http\Responses;

use App\Exceptions\GameErrorCode;
use App\Exceptions\InfraErrorCode;
use Illuminate\Http\JsonResponse;
use Nexus\Core\ValueObjects\ErrorResponse;
use Tests\TestCase;

/**
 * ErrorResponseのテスト
 * 
 * HTTP 299エラーレスポンス（ビジネスロジックエラー）のValueObjectテスト
 */
class ErrorResponseTest extends TestCase
{
    public function test_businessError_creates_http_299_response(): void
    {
        // Arrange & Act
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::PLAYER_NOT_FOUND,
            message: 'Player not found'
        );

        // Assert
        $this->assertInstanceOf(ErrorResponse::class, $errorResponse);
        $this->assertEquals(GameErrorCode::PLAYER_NOT_FOUND, $errorResponse->getErrorCode());
        $this->assertEquals('Player not found', $errorResponse->getMessage());
        $this->assertEquals(299, $errorResponse->getHttpStatus());
    }

    public function test_systemError_creates_http_500_response(): void
    {
        // Arrange & Act
        $errorResponse = ErrorResponse::systemError(
            errorCode: InfraErrorCode::DB_CONNECTION_FAILED,
            message: 'Database connection failed'
        );

        // Assert
        $this->assertInstanceOf(ErrorResponse::class, $errorResponse);
        $this->assertEquals(InfraErrorCode::DB_CONNECTION_FAILED, $errorResponse->getErrorCode());
        $this->assertEquals('Database connection failed', $errorResponse->getMessage());
        $this->assertEquals(500, $errorResponse->getHttpStatus());
    }

    public function test_withStatus_creates_custom_http_status_response(): void
    {
        // Arrange & Act
        $errorResponse = ErrorResponse::withStatus(
            errorCode: 10403,
            message: 'Forbidden',
            httpStatus: 403
        );

        // Assert
        $this->assertEquals(10403, $errorResponse->getErrorCode());
        $this->assertEquals('Forbidden', $errorResponse->getMessage());
        $this->assertEquals(403, $errorResponse->getHttpStatus());
    }

    public function test_toArray_returns_correct_structure(): void
    {
        // Arrange
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::STAMINA_NOT_ENOUGH,
            message: 'Not enough stamina'
        );

        // Act
        $array = $errorResponse->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertEquals(GameErrorCode::STAMINA_NOT_ENOUGH, $array['error_code']);
        $this->assertEquals('Not enough stamina', $array['message']);
    }

    public function test_toJsonResponse_returns_json_response_with_correct_status(): void
    {
        // Arrange
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::PLAYER_NOT_FOUND,
            message: 'Player not found'
        );

        // Act
        $jsonResponse = $errorResponse->toJsonResponse();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
        $this->assertEquals(299, $jsonResponse->getStatusCode());

        $content = json_decode($jsonResponse->getContent(), true);
        $this->assertEquals(GameErrorCode::PLAYER_NOT_FOUND, $content['error_code']);
        $this->assertEquals('Player not found', $content['message']);
    }

    public function test_toJsonResponse_can_override_status_code(): void
    {
        // Arrange
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::PLAYER_NOT_FOUND,
            message: 'Player not found'
        );

        // Act
        $jsonResponse = $errorResponse->toJsonResponse(400);

        // Assert
        $this->assertEquals(400, $jsonResponse->getStatusCode());
    }

    public function test_maskForProduction_masks_message_in_production_env(): void
    {
        // Arrange
        config(['app.env' => 'production']);
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::PLAYER_NOT_FOUND,
            message: 'Detailed error message'
        );

        // Act
        $maskedResponse = $errorResponse->maskForProduction();

        // Assert
        $this->assertEquals('An error occurred. Please contact support.', $maskedResponse->getMessage());
        $this->assertEquals(GameErrorCode::PLAYER_NOT_FOUND, $maskedResponse->getErrorCode());
        $this->assertEquals(299, $maskedResponse->getHttpStatus());
    }

    public function test_maskForProduction_does_not_mask_message_in_local_env(): void
    {
        // Arrange
        config(['app.env' => 'local']);
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::PLAYER_NOT_FOUND,
            message: 'Detailed error message'
        );

        // Act
        $maskedResponse = $errorResponse->maskForProduction();

        // Assert
        $this->assertEquals('Detailed error message', $maskedResponse->getMessage());
    }

    public function test_jsonSerialize_returns_array(): void
    {
        // Arrange
        $errorResponse = ErrorResponse::businessError(
            errorCode: GameErrorCode::STAMINA_NOT_ENOUGH,
            message: 'Not enough stamina'
        );

        // Act
        $serialized = $errorResponse->jsonSerialize();

        // Assert
        $this->assertIsArray($serialized);
        $this->assertEquals(GameErrorCode::STAMINA_NOT_ENOUGH, $serialized['error_code']);
        $this->assertEquals('Not enough stamina', $serialized['message']);
    }
}
