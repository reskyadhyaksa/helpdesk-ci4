<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\User;
use App\Exceptions\BusinessException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class UserCategoryAccessController extends BaseController
{
    public function show(string $userId): string
    {
        $user       = $this->findUser($userId);
        $access     = service('categoryAccess');
        $categories = service('requestCategories')->all();

        return view('admin/users/category_access', [
            'title'      => lang('Admin.categoryAccess.title'),
            'user'       => $user,
            'categories' => $categories,
            'explained'  => $access->explain($user),
            'exceptions' => $access->exceptionsFor($user),
            'roleName'   => service('roles')->find((string) $user->role_id)['name'] ?? '',
        ]);
    }

    public function store(string $userId): RedirectResponse
    {
        $user = $this->findUser($userId);

        if (! $this->validate('categoryAccessException')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $input = $this->validator->getValidated();

        try {
            service('categoryAccess')->addException($user, $input['category_id'], $input['effect'], auth_user()['id']);
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->back()->with('success', lang('Admin.categoryAccess.added'));
    }

    public function delete(string $userId, string $categoryId): RedirectResponse
    {
        try {
            service('categoryAccess')->removeException($this->findUser($userId), $categoryId, auth_user()['id']);
        } catch (BusinessException $e) {
            return redirect()->back()->with('danger', $e->getMessage());
        }

        return redirect()->back()->with('success', lang('Admin.categoryAccess.removed'));
    }

    private function findUser(string $userId): User
    {
        return service('users')->find($userId) ?? throw PageNotFoundException::forPageNotFound();
    }
}
