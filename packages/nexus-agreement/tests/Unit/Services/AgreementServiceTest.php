<?php

namespace NexusAgreement\Tests\Unit\Services;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nexus\Core\Utilities\ClockUtility;
use NexusAgreement\DataTransferObjects\Agreement;
use NexusAgreement\Exceptions\AgreementException;
use NexusAgreement\Repositories\AgreementRepositoryInterface;
use NexusAgreement\Services\AgreementService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AgreementServiceのUnitテスト
 *
 * 未同意規約の絞り込みロジックと同意記録のフローを固定する。
 */
class AgreementServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private AgreementRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-10-15 12:00:00');

        $this->repository = Mockery::mock(AgreementRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function 未同意の必須規約を返す(): void
    {
        $agreement = $this->makeAgreement(id: 1);

        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([$agreement]);
        $this->repository->shouldReceive('selectConsentedAgreementIds')->with(100)->once()->andReturn([]);

        $service = new AgreementService($this->repository);
        $result = $service->getPendingAgreements(100);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->first()->getSysAgreementId());
    }

    #[Test]
    public function 同意済みは除外される(): void
    {
        $agreement1 = $this->makeAgreement(id: 1);
        $agreement2 = $this->makeAgreement(id: 2);

        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([$agreement1, $agreement2]);
        // agreement2 は同意済み
        $this->repository->shouldReceive('selectConsentedAgreementIds')->with(100)->once()->andReturn([2]);

        $service = new AgreementService($this->repository);
        $result = $service->getPendingAgreements(100);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->first()->getSysAgreementId());
    }

    #[Test]
    public function 全件同意済みなら空コレクションを返す(): void
    {
        $agreement = $this->makeAgreement(id: 1);

        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([$agreement]);
        $this->repository->shouldReceive('selectConsentedAgreementIds')->with(100)->once()->andReturn([1]);

        $service = new AgreementService($this->repository);
        $result = $service->getPendingAgreements(100);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function 同意を記録できる(): void
    {
        $agreement = $this->makeAgreement(id: 1);

        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([$agreement]);
        $this->repository->shouldReceive('selectConsentedAgreementIds')->with(100)->once()->andReturn([]);
        $this->repository->shouldReceive('insertConsent')
            ->with(100, 1, '2026-10-15 12:00:00')
            ->once();

        $service = new AgreementService($this->repository);
        $service->consent(100, 1);

        // 例外なく完了したことを確認
        $this->assertTrue(true);
    }

    #[Test]
    public function 存在しない規約_i_dには同意できない(): void
    {
        // 公開済み必須規約には ID=999 は存在しない
        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([]);
        $this->repository->shouldNotReceive('insertConsent');

        $service = new AgreementService($this->repository);

        $this->expectException(AgreementException::class);
        $service->consent(100, 999);
    }

    #[Test]
    public function 既に同意済みの規約への同意は_agreement_exception(): void
    {
        $agreement = $this->makeAgreement(id: 1);

        $this->repository->shouldReceive('selectRequiredPublishedUntil')->with('2026-10-15 12:00:00')->once()->andReturn([$agreement]);
        // 既に同意済み
        $this->repository->shouldReceive('selectConsentedAgreementIds')->with(100)->once()->andReturn([1]);
        $this->repository->shouldNotReceive('insertConsent');

        $service = new AgreementService($this->repository);

        $this->expectException(AgreementException::class);
        $service->consent(100, 1);
    }

    /**
     * テスト用の Agreement を生成する
     */
    private function makeAgreement(int $id): Agreement
    {
        return new Agreement(
            id: $id,
            version: '1.0',
            title: "利用規約 {$id}",
            url: "https://example.com/terms/{$id}",
            isRequired: true,
            publishedAt: '2020-01-01 00:00:00',
        );
    }
}
