<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class TicketController extends BaseController
{
    public function index(): string
    {
        return view('admin/tickets/index', ['title' => lang('Admin.pages.tickets.title')]);
    }

    public function mine(): string
    {
        return view('admin/tickets/mine', ['title' => lang('Admin.pages.myTickets.title')]);
    }
}
