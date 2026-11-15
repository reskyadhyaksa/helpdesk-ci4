<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use CodeIgniter\HTTP\RedirectResponse;

class SettingController extends BaseController
{
    public function index(): string
    {
        $service = service('systemParameters');

        return view('admin/settings/index', [
            'title'     => lang('Admin.pages.settings.title'),
            'groups'    => $service->groups(),
            'phpLimits' => $service->phpLimits(),
        ]);
    }

    public function update(): RedirectResponse
    {
        try {
            service('systemParameters')->update((array) $this->request->getPost('params'));
        } catch (BusinessException $e) {
            return redirect()->to(url_to('admin.settings.index'))->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.settings.index'))->with('success', lang('Admin.settings.saved'));
    }
}
