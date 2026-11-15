<?php

namespace App\Controllers;

use App\Exceptions\BusinessException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Tiket tanpa login. Data kepegawaian dicocokkan dengan HR di service; hasilnya hanya menandai tiket.
 */
class PublicTicketController extends BaseController
{
    public function new(): string
    {
        return view('public/tickets/new', [
            'title'      => lang('Ticket.publicTitle'),
            'categories' => service('tickets')->categoryOptions(),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('publicTicket')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $ticket = service('tickets')->createPublic($this->validator->getValidated(), $this->request->getFileMultiple('attachments') ?? []);
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('public.tickets.new'))->with('success', lang('Ticket.publicCreated', [$ticket['ticket_number']]));
    }
}
