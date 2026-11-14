<?php

namespace Nexus\Core\Tests\Unit\Resource\Services;

use Nexus\Core\Resource\Contracts\DiamondRepositoryInterface;
use Nexus\Core\Resource\DataTransferObjects\DiamondBalance;
use Nexus\Core\Resource\Services\DiamondService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * DiamondServiceのユニットテスト
 *
 * 有償ダイヤ（trx_diamond.paid_amount。platform 別）だけを扱う。
 * 無償ダイヤは trx_item の free_diamond にあり、この Service は触らない。
 */
class DiamondServiceTest extends TestCase
{
    private DiamondService $diamondService;

    private DiamondRepositoryInterface|MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(DiamondRepositoryInterface::class);
        $this->diamondService = new DiamondService($this->mockRepository);
    }

    #[Test]
    public function 指定platformの有償残高を取得できる(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('selectByPlatform')
            ->with(1, 'Apple')
            ->willReturn(new DiamondBalance(1, 'Apple', 500));

        $this->assertSame(500, $this->diamondService->findPaidAmount(1, 'Apple'));
    }

    #[Test]
    public function 行が無いplatformの有償残高は0(): void
    {
        $this->mockRepository->method('selectByPlatform')->willReturn(null);

        $this->assertSame(0, $this->diamondService->findPaidAmount(1, 'Google'));
    }

    #[Test]
    public function 有償残高の取得は他platformの行を読まない(): void
    {
        // 全 platform の合算（selectAllByPlayerId）は呼ばない
        $this->mockRepository->expects($this->never())->method('selectAllByPlayerId');
        $this->mockRepository->method('selectByPlatform')->willReturn(null);

        $this->diamondService->findPaidAmount(1, 'Apple');
    }

    #[Test]
    public function 既存の行に有償ダイヤを加算できる(): void
    {
        $this->mockRepository->method('selectByPlatform')
            ->willReturn(new DiamondBalance(1, 'Apple', 500));

        $this->mockRepository->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(fn (DiamondBalance $dto) => $dto->getPaidAmount() === 700));

        $result = $this->diamondService->addPaidDiamond(1, 'Apple', 200);

        $this->assertSame(700, $result->getPaidAmount());
    }

    #[Test]
    public function 行が無ければ作って有償ダイヤを加算する(): void
    {
        $this->mockRepository->method('selectByPlatform')->willReturn(null);

        $this->mockRepository->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(fn (DiamondBalance $dto) => $dto->getSysPlayerId() === 1
                && $dto->getPlatform() === 'Google'
                && $dto->getPaidAmount() === 300));

        $result = $this->diamondService->addPaidDiamond(1, 'Google', 300);

        $this->assertSame(300, $result->getPaidAmount());
    }

    #[Test]
    public function 加算量0でも初期行が作られる(): void
    {
        $this->mockRepository->method('selectByPlatform')->willReturn(null);
        $this->mockRepository->expects($this->once())->method('persistDiamond');

        $result = $this->diamondService->addPaidDiamond(1, 'Apple', 0);

        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 有償ダイヤを消費できる(): void
    {
        $this->mockRepository->method('selectByPlatform')
            ->willReturn(new DiamondBalance(1, 'Apple', 500));

        $this->mockRepository->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(fn (DiamondBalance $dto) => $dto->getPaidAmount() === 200));

        $this->diamondService->consumePaidDiamond(1, 'Apple', 300);
    }

    #[Test]
    public function 有償残高ちょうどを消費すると0で残る(): void
    {
        $this->mockRepository->method('selectByPlatform')
            ->willReturn(new DiamondBalance(1, 'Apple', 300));

        $this->mockRepository->expects($this->once())
            ->method('persistDiamond')
            ->with($this->callback(fn (DiamondBalance $dto) => $dto->getPaidAmount() === 0));

        $this->diamondService->consumePaidDiamond(1, 'Apple', 300);
    }

    #[Test]
    public function 有償残高が不足していれば例外で何も保存しない(): void
    {
        $this->mockRepository->method('selectByPlatform')
            ->willReturn(new DiamondBalance(1, 'Apple', 100));
        $this->mockRepository->expects($this->never())->method('persistDiamond');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('有償ダイヤモンド残高が不足しています');

        $this->diamondService->consumePaidDiamond(1, 'Apple', 101);
    }

    #[Test]
    public function 行が無いplatformの消費は例外(): void
    {
        $this->mockRepository->method('selectByPlatform')->willReturn(null);
        $this->mockRepository->expects($this->never())->method('persistDiamond');

        $this->expectException(\Exception::class);

        $this->diamondService->consumePaidDiamond(1, 'Google', 1);
    }

    #[Test]
    public function dtoのto_arrayにfree_amountとtotal_amountは含まれない(): void
    {
        $array = (new DiamondBalance(1, 'Apple', 5))->toArray();

        $this->assertSame(['sys_player_id' => 1, 'platform' => 'Apple', 'paid_amount' => 5], $array);
    }
}
