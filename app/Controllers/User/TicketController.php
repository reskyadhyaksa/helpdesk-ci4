<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use App\Services\TicketService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class TicketController extends BaseController
{
    public function index(): string
    {
        $result = service('tickets')->paginateVisible(auth_user(), false, TicketService::SCOPE_MINE);

        return view('user/tickets/index', [
            'title'   => lang('Ticket.mineTitle'),
            'tickets' => $result['tickets'],
            'pager'   => $result['pager'],
        ]);
    }

    public function show(string $id): string
    {
        $service = service('tickets');
        $ticket  = $service->find($id, auth_user(), false) ?? throw PageNotFoundException::forPageNotFound();

        return view('user/tickets/show', [
            'title'      => $ticket['ticket_number'],
            'ticket'     => $ticket,
            'activities' => $service->activities($id),
        ]);
    }

    public function new(): string
    {
        return view('user/tickets/new', [
            'title'      => lang('Ticket.newTitle'),
            'categories' => service('tickets')->categoryOptions(),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('ticket')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('tickets')->create($this->validator->getValidated(), auth_user());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('user.tickets.index'))->with('success', lang('Ticket.created'));
    }
}
