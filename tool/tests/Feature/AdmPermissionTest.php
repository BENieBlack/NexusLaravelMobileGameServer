<?php

namespace Tests\Feature;

use App\Models\Adm\AdmPage;
use App\Models\Adm\AdmRole;
use App\Models\Adm\AdmAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', [
            '--database' => 'admin',
            '--path' => 'database/migrations/adm',
            '--force' => true,
        ]);
    }

    #[Test]
    public function ロールとページを作成して拒否ページを関連付けられる(): void
    {
        $role = AdmRole::create(['id' => 'operator', 'name' => '運用者']);
        $page = AdmPage::create(['id' => 'master.items']);

        $role->deniedPages()->attach($page->id);

        $this->assertTrue($role->deniedPages()->whereKey($page->id)->exists());
        $this->assertTrue($page->denyingRoles()->whereKey($role->id)->exists());
    }

    #[Test]
    public function 同じロールとページの拒否設定は重複できない(): void
    {
        $role = AdmRole::create(['id' => 'viewer', 'name' => '閲覧者']);
        $page = AdmPage::create(['id' => 'dashboard']);

        $role->deniedPages()->attach($page->id);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $role->deniedPages()->attach($page->id);
    }

    #[Test]
    public function adminアカウントは拒否設定に関係なくアクセスできる(): void
    {
        $admin = AdmAccount::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin',
        ]);
        $role = AdmRole::create(['id' => 'restricted', 'name' => '制限ロール']);
        $page = AdmPage::create(['id' => 'restricted.page']);
        $admin->roles()->attach($role->id);
        $role->deniedPages()->attach($page->id);

        $this->assertTrue($admin->canAccessPage($page->id));
    }

    #[Test]
    public function アカウントとロールを多対多で関連付けられる(): void
    {
        $account = AdmAccount::create([
            'name' => 'operator',
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);
        $role = AdmRole::create(['id' => 'operator', 'name' => '運用者']);

        $account->roles()->attach($role->id);

        $this->assertTrue($account->roles()->whereKey($role->id)->exists());
        $this->assertTrue($role->accounts()->whereKey($account->id)->exists());
    }
}
