<?php

namespace Nexus\Core\Tests\Unit\UnitOfWork;

use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\UnitOfWork\UpdatedResources;
use PHPUnit\Framework\TestCase;

class QueryManagerUpdatedResourcesTest extends TestCase
{
    public function test_pull_returns_empty_when_nothing_was_written(): void
    {
        $queryManager = new QueryManager;

        $this->assertInstanceOf(UpdatedResources::class, $queryManager->pullUpdatedResources());
        $this->assertTrue($queryManager->pullUpdatedResources()->isEmpty());
    }

    public function test_discard_is_safe_to_call_without_updates(): void
    {
        $queryManager = new QueryManager;
        $queryManager->discardUpdatedResources();

        $this->assertTrue($queryManager->pullUpdatedResources()->isEmpty());
    }
}
