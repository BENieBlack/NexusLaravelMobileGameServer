<?php

namespace App\Http\Controllers;

use App\Models\Adm\AdmAccount;
use App\Models\Adm\AdmPage;
use App\Models\Adm\AdmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdmManagementController extends Controller
{
    public function accounts(): View
    {
        return view('adm.accounts.index', ['accounts' => AdmAccount::query()->orderBy('id')->get()]);
    }

    public function createAccount(): View
    {
        return view('adm.accounts.create');
    }

    public function editAccount(AdmAccount $account): View
    {
        return view('adm.accounts.edit', compact('account'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admin.adm_account,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        AdmAccount::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('adm.accounts.index')->with('status', 'アカウントを作成しました。');
    }

    public function updateAccount(Request $request, AdmAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admin.adm_account,email,' . $account->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $account->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($data['password'] ?? null) {
            $account->password = Hash::make($data['password']);
        }
        $account->save();

        return redirect()->route('adm.accounts.index')->with('status', 'アカウントを更新しました。');
    }

    public function roles(): View
    {
        return view('adm.roles.index', ['roles' => AdmRole::query()->orderBy('id')->get()]);
    }

    public function createRole(): View
    {
        return view('adm.roles.create');
    }

    public function editRole(AdmRole $role): View
    {
        return view('adm.roles.edit', compact('role'));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:admin.adm_role,id'],
            'name' => ['required', 'string', 'max:255', 'unique:admin.adm_role,name'],
        ]);

        AdmRole::create($data);

        return redirect()->route('adm.roles.index')->with('status', 'ロールを作成しました。');
    }

    public function updateRole(Request $request, AdmRole $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:admin.adm_role,name,' . $role->id . ',id'],
        ]);

        $role->update($data);

        return redirect()->route('adm.roles.index')->with('status', 'ロールを更新しました。');
    }

    public function pages(): View
    {
        return view('adm.pages.index', ['pages' => AdmPage::query()->orderBy('id')->get()]);
    }

    public function createPage(): View
    {
        return view('adm.pages.create');
    }

    public function editPage(AdmPage $page): View
    {
        return view('adm.pages.edit', compact('page'));
    }

    public function storePage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:191', 'regex:/^[a-zA-Z0-9_.:-]+$/', 'unique:admin.adm_page,id'],
        ]);

        AdmPage::create($data);

        return redirect()->route('adm.pages.index')->with('status', 'ページを作成しました。');
    }

    public function updatePage(Request $request, AdmPage $page): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:191', 'regex:/^[a-zA-Z0-9_.:-]+$/', 'unique:admin.adm_page,id,' . $page->id . ',id'],
        ]);

        if ($data['id'] !== $page->id) {
            $page->id = $data['id'];
            $page->save();
        }

        return redirect()->route('adm.pages.index')->with('status', 'ページを更新しました。');
    }
}
