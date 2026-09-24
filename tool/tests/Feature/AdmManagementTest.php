<?php

namespace Tests\Feature;

use App\Models\Adm\AdmAccount;
use App\Models\Adm\AdmPage;
use App\Models\Adm\AdmRole;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'admin', '--path' => 'database/migrations/adm', '--force' => true]);
        $this->actingAs(AdmAccount::create([
            'name' => 'operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('password'),
        ]));
    }

    #[Test]
    public function アカウント作成画面から登録できる(): void
    {
        $this->post(route('adm.accounts.store'), [
            'name' => 'admin2',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('adm_account', ['email' => 'admin2@example.com'], 'admin');
    }

    #[Test]
    public function ロールとページを作成できる(): void
    {
        $this->post(route('adm.roles.store'), ['id' => 'operator', 'name' => '運用者'])->assertSessionHas('status');
        $this->post(route('adm.pages.store'), ['id' => 'master.items'])->assertSessionHas('status');

        $this->assertDatabaseHas('adm_role', ['id' => 'operator'], 'admin');
        $this->assertDatabaseHas('adm_page', ['id' => 'master.items'], 'admin');
    }

    #[Test]
    public function 一覧画面に作成ボタンと編集リンクがある(): void
    {
        AdmRole::create(['id' => 'operator', 'name' => '運用者']);
        AdmPage::create(['id' => 'master.items']);

        $this->get(route('adm.accounts.index'))->assertOk()->assertSee('作成');
        $this->get(route('adm.roles.index'))->assertOk()->assertSee(route('adm.roles.edit', 'operator'));
        $this->get(route('adm.pages.index'))->assertOk()->assertSee(route('adm.pages.edit', 'master.items'));
    }

    #[Test]
    public function 一覧から編集内容を更新できる(): void
    {
        $role = AdmRole::create(['id' => 'operator', 'name' => '運用者']);
        $page = AdmPage::create(['id' => 'master.items']);

        $this->put(route('adm.roles.update', $role), ['name' => '管理者'])->assertRedirect(route('adm.roles.index'));
        $this->put(route('adm.pages.update', $page), ['id' => 'master.units'])->assertRedirect(route('adm.pages.index'));

        $this->assertDatabaseHas('adm_role', ['id' => 'operator', 'name' => '管理者'], 'admin');
        $this->assertDatabaseHas('adm_page', ['id' => 'master.units'], 'admin');
    }
}
