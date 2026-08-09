<?php

namespace NexusGuild\Tests;

use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Constants\GuildRole;
use PHPUnit\Framework\TestCase;

/**
 * ConstantsTest
 *
 * 定数クラスのテスト
 */
class ConstantsTest extends TestCase
{
    /**
     * @test
     */
    public function guild_apply_status_all_returns_expected_statuses(): void
    {
        $statuses = GuildApplyStatus::all();

        $this->assertCount(3, $statuses);
        $this->assertContains('applied', $statuses);
        $this->assertContains('accepted', $statuses);
        $this->assertContains('rejected', $statuses);
    }

    /**
     * @test
     */
    public function guild_apply_status_is_valid_returns_true_for_valid_status(): void
    {
        $this->assertTrue(GuildApplyStatus::isValid('applied'));
        $this->assertTrue(GuildApplyStatus::isValid('accepted'));
        $this->assertTrue(GuildApplyStatus::isValid('rejected'));
    }

    /**
     * @test
     */
    public function guild_apply_status_is_valid_returns_false_for_invalid_status(): void
    {
        $this->assertFalse(GuildApplyStatus::isValid('invalid'));
        $this->assertFalse(GuildApplyStatus::isValid('pending'));
    }

    /**
     * @test
     */
    public function guild_role_all_returns_expected_roles(): void
    {
        $roles = GuildRole::all();

        $this->assertCount(3, $roles);
        $this->assertContains('master', $roles);
        $this->assertContains('sub_master', $roles);
        $this->assertContains('member', $roles);
    }

    /**
     * @test
     */
    public function guild_role_is_valid_returns_true_for_valid_role(): void
    {
        $this->assertTrue(GuildRole::isValid('master'));
        $this->assertTrue(GuildRole::isValid('sub_master'));
        $this->assertTrue(GuildRole::isValid('member'));
    }

    /**
     * @test
     */
    public function guild_role_is_valid_returns_false_for_invalid_role(): void
    {
        $this->assertFalse(GuildRole::isValid('invalid'));
        $this->assertFalse(GuildRole::isValid('admin'));
    }
}
