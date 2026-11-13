<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class HolidayController extends BaseController
{
    public function index(): string
    {
        return view('admin/holidays/index', ['title' => lang('Admin.pages.holidays.title')]);
    }
}
