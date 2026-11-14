<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class RequestCategoryController extends BaseController
{
    public function index(): string
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $result  = service('requestCategories')->paginate($keyword);

        return view('admin/request_categories/index', [
            'title'      => lang('Admin.pages.requestCategories.title'),
            'keyword'    => $keyword,
            'categories' => $result['categories'],
            'jobTitles'  => $result['jobTitles'],
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
            service('requestCategories')->create($this->validator->getValidated());
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
            service('requestCategories')->update($category, $this->validator->getValidated());
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

    /**
     * Data dropdown form. Kategori yang sedang diubah tidak boleh menjadi induknya sendiri.
     *
     * @param list<string> $savedJobTitles
     */
    private function formOptions(?string $categoryId, ?string $savedSlaId, array $savedJobTitles): array
    {
        $categories = service('requestCategories');
        $parents    = array_filter($categories->topLevel(), static fn (array $parent) => $parent['id'] !== $categoryId);
        $titles     = $categories->jobTitleOptions($savedJobTitles);

        return [
            'parentOptions'   => array_column($parents, 'name', 'id'),
            'slaOptions'      => service('slaPolicies')->activeOptions($savedSlaId),
            'jobTitleOptions' => array_combine($titles, $titles),
            'savedJobTitles'  => $savedJobTitles,
        ];
    }
}
