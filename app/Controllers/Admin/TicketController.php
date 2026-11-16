<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\TicketStatus;
use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Services\AttachmentService;
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

    public function new(): string
    {
        return view('admin/tickets/new', [
            'title'      => lang('Ticket.adminNewTitle'),
            'categories' => service('tickets')->categoryOptions(),
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('adminTicket')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $ticket = service('tickets')->create($this->validator->getValidated(), auth_user(), $this->request->getFileMultiple('attachments') ?? []);
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return $this->back($ticket['id'])->with('success', lang('Ticket.created'));
    }

    public function show(string $id): string
    {
        $service = service('tickets');
        $ticket  = $service->find($id, auth_user(), $this->canViewAll()) ?? throw PageNotFoundException::forPageNotFound();

        $data = [
            'title'      => $ticket['ticket_number'],
            'ticket'     => $service->withSlaRemaining([$ticket])[0],
            'activities' => $service->activities($id),
            'pics'       => $service->picCandidates($ticket),
            'thread'     => service('ticketComments')->thread($id, true),
        ];

        // Modal di daftar tiket mengambil isi yang sama tanpa layout (js/pages/admin/ticket-modal.js)
        return view($this->request->isAJAX() ? 'admin/tickets/_modal_card' : 'admin/tickets/show', $data);
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
        return $this->fileResponse($id, inline: false);
    }

    /**
     * Tampilkan lampiran di browser tanpa mengunduh. Hanya jenis yang aman (AttachmentService::canPreview).
     */
    public function preview(string $id): DownloadResponse
    {
        return $this->fileResponse($id, inline: true);
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
        $tickets  = service('tickets');
        $filters  = $tickets->normalizeFilters($this->request->getGet());
        $isKanban = $filters['view'] === 'kanban';
        $result   = $isKanban ? ['tickets' => [], 'pager' => null] : $tickets->paginateVisible(auth_user(), $this->canViewAll(), $scope, $filters);

        return view($view, [
            'title'     => $title,
            'tickets'   => $result['tickets'],
            'pager'     => $result['pager'],
            'board'     => $isKanban ? $tickets->board(auth_user(), $this->canViewAll(), $scope, $filters) : [],
            'filters'   => $filters,
            'tabCounts' => $tickets->tabCounts(auth_user(), $this->canViewAll(), $scope, $filters),
            'options'   => $tickets->filterOptions(),
        ]);
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

    private function fileResponse(string $id, bool $inline): DownloadResponse
    {
        try {
            $file = service('ticketComments')->attachmentForDownload($id, auth_user(), $this->canViewAll(), true);
        } catch (BusinessException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $response = $this->response->download($file['path'], null)->setFileName($file['name'])->setHeader('X-Content-Type-Options', 'nosniff');

        if (! $inline) {
            return $response;
        }

        if (! AttachmentService::canPreview($file['type'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $response->inline()->setContentType($file['type']);

        // Gambar dan teks tidak butuh apa pun selain tampil: sandbox mencegah skrip dari berkas ini berjalan.
        // PDF dibiarkan agar penampil bawaan browser bisa dipakai.
        if ($file['type'] !== AttachmentService::TYPE_PDF) {
            $response->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        }

        return $response;
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
