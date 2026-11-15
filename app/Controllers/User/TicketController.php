<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use App\Services\TicketService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
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
            'thread'     => service('ticketComments')->thread($id, false),
        ]);
    }

    public function comment(string $id): RedirectResponse
    {
        $back = redirect()->to(url_to('user.tickets.show', $id));

        if (! $this->validate('ticketComment')) {
            return $back->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('ticketComments')->add($id, auth_user(), false, false, $this->validator->getValidated(), $this->request->getFileMultiple('attachments') ?? []);
        } catch (BusinessException $e) {
            return $back->withInput()->with('danger', $e->getMessage());
        }

        return $back->with('success', lang('Ticket.commentAdded'));
    }

    public function attachment(string $id): DownloadResponse
    {
        try {
            $file = service('ticketComments')->attachmentForDownload($id, auth_user(), false, false);
        } catch (BusinessException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($file['path'], null)->setFileName($file['name'])->setHeader('X-Content-Type-Options', 'nosniff');
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
            service('tickets')->create($this->validator->getValidated(), auth_user(), $this->request->getFileMultiple('attachments') ?? []);
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('user.tickets.index'))->with('success', lang('Ticket.created'));
    }
}
