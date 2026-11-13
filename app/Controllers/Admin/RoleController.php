<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class RoleController extends BaseController
{
    public function index(): string
    {
        $result = service('roles')->paginate();

        return view('admin/roles/index', [
            'title' => lang('Admin.pages.roles.title'),
            'roles' => $result['roles'],
            'pager' => $result['pager'],
        ]);
    }

    public function new(): string
    {
        return view('admin/roles/new', [
            'title'   => lang('Admin.roles.createTitle'),
            'role'    => ['id' => null, 'name' => ''],
            'granted' => [],
            'locked'  => [],
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('role')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('roles')->create($this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.roles.index'))->with('success', lang('Admin.roles.created'));
    }

    public function edit(string $id): string
    {
        $roles = service('roles');
        $role  = $this->findOrFail($id);

        return view('admin/roles/edit', [
            'title'   => lang('Admin.roles.editTitle'),
            'role'    => $role,
            'granted' => $roles->permissionSlugs($role),
            'locked'  => $roles->lockedPermissions($role),
        ]);
    }

    public function update(string $id): RedirectResponse
    {
        $role = $this->findOrFail($id);

        if (! $this->validate('role')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('roles')->update($role, $this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.roles.index'))->with('success', lang('Admin.roles.updated'));
    }

    public function delete(string $id): RedirectResponse
    {
        try {
            service('roles')->delete($this->findOrFail($id));
        } catch (BusinessException $e) {
            return redirect()->to(url_to('admin.roles.index'))->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.roles.index'))->with('success', lang('Admin.roles.deleted'));
    }

    private function findOrFail(string $id): array
    {
        return service('roles')->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
