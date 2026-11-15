<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\TicketStatus;
use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Services\TicketService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

class TicketController extends BaseController
{
    public function index(): string
    {
        return $this->listing('admin/tickets/index', lang('Admin.pages.tickets.title'), null);
    }

    public function mine(): string
    {
        return $this->listing('admin/tickets/mine', lang('Admin.pages.myTickets.title'), TicketService::SCOPE_ASSIGNED);
    }

    public function show(string $id): string
    {
        $service = service('tickets');
        $ticket  = $service->find($id, auth_user(), $this->canViewAll()) ?? throw PageNotFoundException::forPageNotFound();

        return view('admin/tickets/show', [
            'title'      => $ticket['ticket_number'],
            'ticket'     => $ticket,
            'activities' => $service->activities($id),
            'pics'       => $service->pics(),
            'thread'     => service('ticketComments')->thread($id, true),
        ]);
    }

    public function comment(string $id): RedirectResponse
    {
        if (! $this->validate('ticketComment')) {
            return $this->back($id)->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('ticketComments')->add($id, auth_user(), $this->canViewAll(), true, $this->validator->getValidated(), $this->request->getFileMultiple('attachments') ?? []);
        } catch (BusinessException $e) {
            return $this->back($id)->withInput()->with('danger', $e->getMessage());
        }

        return $this->back($id)->with('success', lang('Ticket.commentAdded'));
    }

    public function attachment(string $id): DownloadResponse
    {
        try {
            $file = service('ticketComments')->attachmentForDownload($id, auth_user(), $this->canViewAll(), true);
        } catch (BusinessException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($file['path'], null)->setFileName($file['name'])->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function updateStatus(string $id): RedirectResponse
    {
        if (! $this->validate('ticketStatus')) {
            return $this->back($id)->with('danger', implode(' ', $this->validator->getErrors()));
        }

        $status = TicketStatus::from($this->validator->getValidated()['status']);

        return $this->perform($id, lang('Ticket.statusUpdated'), static function (TicketService $tickets) use ($id, $status): void {
            $tickets->changeStatus($id, $status, auth_user()['id']);
        });
    }

    public function assign(string $id): RedirectResponse
    {
        if (! $this->validate('ticketAssign')) {
            return $this->back($id)->with('danger', implode(' ', $this->validator->getErrors()));
        }

        $post = $this->validator->getValidated();

        return $this->perform($id, lang('Ticket.picUpdated'), static function (TicketService $tickets) use ($id, $post): void {
            $tickets->assign($id, $post['role'], $post['user_id'] ?: null, auth_user()['id']);
        });
    }

    private function listing(string $view, string $title, ?string $scope): string
    {
        $result = service('tickets')->paginateVisible(auth_user(), $this->canViewAll(), $scope);

        return view($view, ['title' => $title, 'tickets' => $result['tickets'], 'pager' => $result['pager']]);
    }

    /**
     * Jalankan aksi service pada tiket yang terlihat oleh admin ini, lalu kembali ke halaman detail.
     */
    private function perform(string $id, string $successMessage, callable $action): RedirectResponse
    {
        $service = service('tickets');

        if ($service->find($id, auth_user(), $this->canViewAll()) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $action($service);
        } catch (BusinessException $e) {
            return $this->back($id)->with('danger', $e->getMessage());
        }

        return $this->back($id)->with('success', $successMessage);
    }

    private function back(string $id): RedirectResponse
    {
        return redirect()->to(url_to('admin.tickets.show', $id));
    }

    private function canViewAll(): bool
    {
        return auth_can(PermissionModel::ADMIN_TICKETS_VIEW_ALL);
    }
}
