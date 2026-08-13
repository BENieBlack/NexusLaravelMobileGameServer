<?php

namespace Tests\Unit\UseCases\Player;

use App\Domain\Player\UseCases\PlayerMeUseCase;
use App\Exceptions\SystemDataException;
use App\Http\Responses\Player\MeResponse;
use App\Models\Sys\SysPlayer;
use App\Repositories\Sys\SysPlayerRepository;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class MeUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private PlayerMeUseCase $useCase;

    private SysPlayerRepository $playerRepository;

    /**
     * Define database connections to migrate for this test
     */
    protected function connectionsToMigrate(): array
    {
        return [
            'sys' => 'database/migrations/sys',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Repositoryを作成
        $this->playerRepository = new SysPlayerRepository(new SysPlayer);

        // UseCaseを作成
        $this->useCase = new PlayerMeUseCase($this->playerRepository);

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * テスト用のプレイヤーを作成するヘルパーメソッド
     */
    private function createPlayer(): SysPlayer
    {
        return $this->playerRepository->insertPlayerAndCommit();
    }

    /**
     * Test handle returns player information successfully
     */
    public function test_handle_returns_player_information(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();

        // Act
        $response = $this->useCase->exec($sysPlayer->id);

        // Assert
        $this->assertInstanceOf(MeResponse::class, $response);
        $this->assertEquals($sysPlayer->my_id, $response->myId);
        $this->assertEquals($sysPlayer->name, $response->name);
    }

    /**
     * Test handle returns correct my_id
     */
    public function test_handle_returns_correct_my_id(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();

        // Act
        $response = $this->useCase->exec($sysPlayer->id);

        // Assert
        $this->assertNotEmpty($response->myId);
        $this->assertEquals(8, strlen($response->myId));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $response->myId);
    }

    /**
     * Test handle throws exception when player not found
     */
    public function test_handle_throws_exception_when_player_not_found(): void
    {
        // Arrange
        $nonExistentPlayerId = 99999;

        // Assert & Act
        $this->expectException(SystemDataException::class);

        $this->useCase->exec($nonExistentPlayerId);
    }

    /**
     * Test validation passes for existing player
     */
    public function test_validation_passes_for_existing_player(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();

        // Act & Assert - 例外が発生しないことを確認
        $this->useCase->validation($sysPlayer->id);
        $this->assertTrue(true); // バリデーションが成功した
    }

    /**
     * Test validation throws exception for non-existent player
     */
    public function test_validation_throws_exception_for_non_existent_player(): void
    {
        // Arrange
        $nonExistentPlayerId = 99999;

        // Assert & Act
        $this->expectException(SystemDataException::class);

        $this->useCase->validation($nonExistentPlayerId);
    }

    /**
     * Test handle returns name for new player
     */
    public function test_handle_returns_name_for_new_player(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();

        // Act
        $response = $this->useCase->exec($sysPlayer->id);

        // Assert - 新規プレイヤーの名前はランダムに生成される（8文字）
        $this->assertNotNull($response->name);
        $this->assertEquals($sysPlayer->name, $response->name);
        $this->assertEquals(8, strlen($response->name));
    }

    /**
     * Test handle returns updated name if player name is set
     */
    public function test_handle_returns_updated_name(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();

        // プレイヤーの名前を設定
        $sysPlayer->name = 'Test Player Name';
        $sysPlayer->save();

        // Act
        $response = $this->useCase->exec($sysPlayer->id);

        // Assert
        $this->assertEquals('Test Player Name', $response->name);
    }

    /**
     * Test handle works for multiple different players
     */
    public function test_handle_works_for_multiple_players(): void
    {
        // Arrange
        $sysPlayer1 = $this->createPlayer();
        $sysPlayer2 = $this->createPlayer();
        $sysPlayer3 = $this->createPlayer();

        // Act
        $response1 = $this->useCase->exec($sysPlayer1->id);
        $response2 = $this->useCase->exec($sysPlayer2->id);
        $response3 = $this->useCase->exec($sysPlayer3->id);

        // Assert - すべて異なるmy_idを持つ
        $this->assertNotEquals($response1->myId, $response2->myId);
        $this->assertNotEquals($response2->myId, $response3->myId);
        $this->assertNotEquals($response1->myId, $response3->myId);

        // Assert - それぞれ正しいmy_idを返す
        $this->assertEquals($sysPlayer1->my_id, $response1->myId);
        $this->assertEquals($sysPlayer2->my_id, $response2->myId);
        $this->assertEquals($sysPlayer3->my_id, $response3->myId);
    }

    /**
     * Test toArray returns correct structure
     */
    public function test_to_array_returns_correct_structure(): void
    {
        // Arrange
        $sysPlayer = $this->createPlayer();
        $sysPlayer->name = 'Test Name';
        $sysPlayer->save();

        // Act
        $response = $this->useCase->exec($sysPlayer->id);
        $array = $response->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('my_id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertEquals($sysPlayer->my_id, $array['my_id']);
        $this->assertEquals('Test Name', $array['name']);
    }
}
