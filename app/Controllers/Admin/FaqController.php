<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FaqController extends BaseController
{
    public function index(): string
    {
        return view('admin/faqs/index', ['title' => lang('Admin.pages.faqs.title')]);
    }
}
