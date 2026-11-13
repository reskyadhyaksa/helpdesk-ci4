<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class ReportController extends BaseController
{
    public function index(): string
    {
        return view('admin/reports/index', ['title' => lang('Admin.pages.reports.title')]);
    }
}
