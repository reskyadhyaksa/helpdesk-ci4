<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\SlaPolicy;
use App\Exceptions\BusinessException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class SlaPolicyController extends BaseController
{
    public function index(): string
    {
        $result = service('slaPolicies')->paginate();

        return view('admin/sla_policies/index', [
            'title'    => lang('Admin.pages.slaPolicies.title'),
            'policies' => $result['policies'],
            'pager'    => $result['pager'],
        ]);
    }

    public function new(): string
    {
        return view('admin/sla_policies/new', [
            'title'  => lang('Admin.sla.createTitle'),
            // Default sama dengan kolom database: hari libur dan hari off tidak dihitung
            'policy' => new SlaPolicy(['is_active' => true, 'is_holiday' => true, 'is_off_day' => true, 'is_work_hours' => true]),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('slaPolicy')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('slaPolicies')->create($this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.sla-policies.index'))->with('success', lang('Admin.sla.created'));
    }

    public function edit(string $id): string
    {
        return view('admin/sla_policies/edit', [
            'title'  => lang('Admin.sla.editTitle'),
            'policy' => $this->findOrFail($id),
        ]);
    }

    public function update(string $id): RedirectResponse
    {
        $policy = $this->findOrFail($id);

        if (! $this->validate('slaPolicy')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('slaPolicies')->update($policy, $this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.sla-policies.index'))->with('success', lang('Admin.sla.updated'));
    }

    public function delete(string $id): RedirectResponse
    {
        service('slaPolicies')->delete($this->findOrFail($id));

        return redirect()->to(url_to('admin.sla-policies.index'))->with('success', lang('Admin.sla.deleted'));
    }

    private function findOrFail(string $id): SlaPolicy
    {
        return service('slaPolicies')->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
