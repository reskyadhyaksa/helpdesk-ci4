<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class UserController extends BaseController
{
    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));

        // Filter profil dari query string, multi-pilih (jabatan[]=A&jabatan[]=B); nama parameter = nama kolom.
        // Filter tanpa nilai diabaikan.
        $filters = [];

        foreach (UserModel::FILTER_COLUMNS as $column) {
            $values = array_values(array_filter(
                array_map('trim', array_map('strval', (array) $this->request->getGet($column))),
                static fn (string $value): bool => $value !== '',
            ));

            if ($values !== []) {
                $filters[$column] = $values;
            }
        }

        $users  = service('users');
        $result = $users->paginate($keyword, $filters);

        return view('admin/users/index', [
            'title'         => lang('Admin.pages.users.title'),
            'users'         => $result['users'],
            'pager'         => $result['pager'],
            'roles'         => service('roles')->all(),
            'keyword'       => $keyword,
            'filters'       => $filters,
            'filterOptions' => $users->filterOptions(),
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
