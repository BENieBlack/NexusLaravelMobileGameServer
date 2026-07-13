<?php

namespace LaravelUnitOfWork\Tests;

use LaravelUnitOfWork\Persistence\QueryManager\UpdateQueryBuilder;
use PHPUnit\Framework\TestCase;

class UpdateQueryBuilderTest extends TestCase
{
    public function testBuildRelativeUpdateWithPositiveChange()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['name' => 'Updated', 'amount' => 100],
            ['amount' => 50], // Relative change: +50
            ['id' => 1]
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('bindings', $result);
        
        // SQL should have regular update and relative update
        $this->assertStringContainsString('`name` = ?', $result['sql']);
        $this->assertStringContainsString('`amount` = `amount` + 50', $result['sql']);
        $this->assertStringContainsString('where (`id` = ?)', $result['sql']);
        
        // Bindings should contain name value and where condition value
        $this->assertEquals(['Updated', 1], $result['bindings']);
    }

    public function testBuildRelativeUpdateWithNegativeChange()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['amount' => 100],
            ['amount' => -30], // Relative change: -30
            ['id' => 2]
        );
        
        $this->assertStringContainsString('`amount` = `amount` - 30', $result['sql']);
        $this->assertEquals([2], $result['bindings']);
    }

    public function testBuildRelativeUpdateWithZeroChange()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['name' => 'Test', 'amount' => 100],
            ['amount' => 0], // Zero change should be ignored
            ['id' => 3]
        );
        
        // Zero change should not appear in SQL
        $this->assertStringNotContainsString('`amount` = `amount`', $result['sql']);
        $this->assertStringContainsString('`name` = ?', $result['sql']);
        $this->assertEquals(['Test', 3], $result['bindings']);
    }

    public function testBuildRelativeUpdateWithMultipleColumns()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['name' => 'Test', 'gold' => 1000, 'exp' => 500],
            ['gold' => 100, 'exp' => -50],
            ['id' => 4]
        );
        
        $this->assertStringContainsString('`name` = ?', $result['sql']);
        $this->assertStringContainsString('`gold` = `gold` + 100', $result['sql']);
        $this->assertStringContainsString('`exp` = `exp` - 50', $result['sql']);
        $this->assertEquals(['Test', 4], $result['bindings']);
    }

    public function testBuildRelativeUpdateWithMultipleWhereConditions()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['amount' => 100],
            ['amount' => 25],
            ['player_id' => 123, 'item_id' => 456]
        );
        
        $this->assertStringContainsString('where (`player_id` = ? and `item_id` = ?)', $result['sql']);
        $this->assertEquals([123, 456], $result['bindings']);
    }

    public function testBuildRelativeUpdateWithNoRelativeChanges()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'test_table',
            ['name' => 'Test', 'value' => 999],
            [], // No relative changes
            ['id' => 5]
        );
        
        $this->assertStringContainsString('`name` = ?', $result['sql']);
        $this->assertStringContainsString('`value` = ?', $result['sql']);
        $this->assertEquals(['Test', 999, 5], $result['bindings']);
    }

    public function testBuildRelativeUpdateGeneratesCorrectSqlStructure()
    {
        $builder = new UpdateQueryBuilder();
        
        $result = $builder->buildRelativeUpdate(
            'players',
            ['level' => 10, 'gold' => 5000],
            ['gold' => 1000],
            ['id' => 100]
        );
        
        // Verify SQL structure: UPDATE table SET ... WHERE ...
        $this->assertMatchesRegularExpression(
            '/^update `players` set .+ where \(.+\)$/',
            $result['sql']
        );
    }
}
