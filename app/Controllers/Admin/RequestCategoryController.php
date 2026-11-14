<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class RequestCategoryController extends BaseController
{
    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $filters = [];

        // Filter multi-pilih dari query string (job_title[]=A&job_title[]=B); nama parameter = kunci filter.
        // Filter tanpa nilai diabaikan.
        foreach (['parent_id', 'job_title', 'role_id'] as $name) {
            $values = array_values(array_filter(
                array_map('trim', array_map('strval', (array) $this->request->getGet($name))),
                static fn (string $value): bool => $value !== '',
            ));

            if ($values !== []) {
                $filters[$name] = $values;
            }
        }

        $categories = service('requestCategories');
        $result     = $categories->paginate($keyword, $filters);

        return view('admin/request_categories/index', [
            'title'      => lang('Admin.pages.requestCategories.title'),
            'keyword'    => $keyword,
            'filters'    => $filters,
            'filterOptions' => $categories->filterOptions(),
            'categories' => $result['categories'],
            'jobTitles'  => $result['jobTitles'],
            'roles'      => $result['roles'],
            'pager'      => $result['pager'],
        ]);
    }

    public function new(): string
    {
        return view('admin/request_categories/new', [
            'title'    => lang('Admin.requestCategory.createTitle'),
            'category' => ['id' => null, 'parent_id' => null, 'name' => '', 'description' => null, 'sla_policy_id' => null, 'is_active' => 1],
        ] + $this->formOptions(null, null, []));
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('requestCategory')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('requestCategories')->create($this->validator->getValidated(), auth_user()['id'], $this->canManageUserAccess());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.request-categories.index'))->with('success', lang('Admin.requestCategory.created'));
    }

    public function edit(string $id): string
    {
        $category = $this->findOrFail($id);

        return view('admin/request_categories/edit', [
            'title'    => lang('Admin.requestCategory.editTitle'),
            'category' => $category,
        ] + $this->formOptions($category['id'], $category['sla_policy_id'], service('requestCategories')->jobTitlesFor($category['id'])));
    }

    public function update(string $id): RedirectResponse
    {
        $category = $this->findOrFail($id);

        if (! $this->validate('requestCategory')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('requestCategories')->update($category, $this->validator->getValidated(), auth_user()['id'], $this->canManageUserAccess());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.request-categories.index'))->with('success', lang('Admin.requestCategory.updated'));
    }

    public function delete(string $id): RedirectResponse
    {
        try {
            service('requestCategories')->delete($this->findOrFail($id));
        } catch (BusinessException $e) {
            return redirect()->back()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.request-categories.index'))->with('success', lang('Admin.requestCategory.deleted'));
    }

    private function findOrFail(string $id): array
    {
        return service('requestCategories')->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function canManageUserAccess(): bool
    {
        return service('auth')->can(PermissionModel::ADMIN_USERS_CATEGORY_ACCESS);
    }

    /**
     * Data form. Kategori yang sedang diubah tidak boleh menjadi induknya sendiri; akun sendiri tidak
     * ditawarkan di pilihan user karena pengecualian akun sendiri tidak boleh diubah.
     *
     * @param list<string> $savedJobTitles
     */
    private function formOptions(?string $categoryId, ?string $savedSlaId, array $savedJobTitles): array
    {
        $categories  = service('requestCategories');
        $parents     = array_filter($categories->topLevel(), static fn (array $parent) => $parent['id'] !== $categoryId);
        $titles      = $categories->jobTitleOptions($savedJobTitles);
        $userOptions = $categories->userOptions();

        unset($userOptions[auth_user()['id']]);

        return [
            'parentOptions'       => array_column($parents, 'name', 'id'),
            'slaOptions'          => service('slaPolicies')->activeOptions($savedSlaId),
            'jobTitleOptions'     => array_combine($titles, $titles),
            'roleOptions'         => $categories->roleOptions(),
            'userOptions'         => $userOptions,
            'accessEntries'       => $categories->accessEntries($categoryId, $this->submittedAccess()),
            'canManageUserAccess' => $this->canManageUserAccess(),
            'actorId'             => auth_user()['id'],
        ];
    }

    /**
     * Akses yang dikirim pada form yang gagal disimpan (null = form dibuka pertama kali). Pilihan kosong tidak
     * terkirim sama sekali, jadi kehadiran field `name` (selalu terkirim) menandai tampilan ulang.
     *
     * @return array{job_titles: array, role_ids: array, user_access: array}|null
     */
    private function submittedAccess(): ?array
    {
        if ($this->request->getOldInput('name') === null) {
            return null;
        }

        return [
            'job_titles'  => (array) $this->request->getOldInput('job_titles'),
            'role_ids'    => (array) $this->request->getOldInput('role_ids'),
            'user_access' => (array) $this->request->getOldInput('user_access'),
        ];
    }
}
