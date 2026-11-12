<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('user/dashboard', ['title' => 'Dashboard']);
    }
}
