<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class RequestCategoryController extends BaseController
{
    public function index(): string
    {
        return view('admin/request_categories/index', ['title' => lang('Admin.pages.requestCategories.title')]);
    }
}
