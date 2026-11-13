<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FormController extends BaseController
{
    public function index(): string
    {
        return view('admin/forms/index', ['title' => lang('Admin.pages.forms.title')]);
    }
}
