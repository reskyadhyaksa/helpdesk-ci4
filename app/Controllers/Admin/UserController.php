<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class UserController extends BaseController
{
    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $result  = service('users')->paginate($keyword);

        return view('admin/users/index', [
            'title'   => lang('Admin.pages.users.title'),
            'users'   => $result['users'],
            'pager'   => $result['pager'],
            'roles'   => service('roles')->all(),
            'keyword' => $keyword,
        ]);
    }

    public function updateRole(string $id): RedirectResponse
    {
        $user = service('users')->find($id) ?? throw PageNotFoundException::forPageNotFound();

        if (! $this->validate('userRole')) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        try {
            service('users')->assignRole($user, $this->validator->getValidated()['role_id'], auth_user()['id']);
        } catch (BusinessException $e) {
            return redirect()->back()->with('danger', $e->getMessage());
        }

        return redirect()->back()->with('success', lang('Admin.users.roleUpdated'));
    }
}
