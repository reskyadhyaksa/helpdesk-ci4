<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class TicketModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const CHANNEL_PORTAL      = 'PORTAL';
    public const CHANNEL_PUBLIC_FORM = 'PUBLIC_FORM';

    protected $table            = 'tickets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = [
        'ticket_number', 'channel', 'status', 'requester_id', 'requester_nip', 'requester_name', 'requester_phone',
        'requester_email', 'requester_organization', 'is_requester_verified', 'assignee_id', 'resolver_id',
        'request_category_id', 'sla_policy_id', 'priority', 'subject', 'description', 'response_due_at', 'resolution_due_at',
    ];

    protected $validationRules = [
        'ticket_number'       => 'required|max_length[20]|is_unique[tickets.ticket_number,id,{id}]',
        'requester_id'        => 'permit_empty|exact_length[36]',
        'request_category_id' => 'required|exact_length[36]',
        'subject'             => 'required|max_length[150]',
        'description'         => 'required',
    ];

    /**
     * Satu-satunya tempat aturan "siapa boleh melihat tiket" ditulis: pemohon, pengaju, PIC (Helpdesk atau Resolver),
     * ATAU kategori tiket ada di $categoryIds (CategoryAccessService::effectiveCategoryIds), ATAU boleh melihat semua.
     * Pemohon dan pengaju dicocokkan lewat NIK, jadi pemohon yang belum pernah login tetap melihatnya setelah login pertama.
     * Daftar kosong tidak dikirim ke whereIn.
     *
     * @param array{id: string, employee_no?: string} $viewer
     * @param list<string>                            $categoryIds
     */
    public function visibleTo(array $viewer, array $categoryIds, bool $canViewAll): static
    {
        if ($canViewAll) {
            return $this;
        }

        $this->groupStart()
            ->where('tickets.requester_id', $viewer['id'])
            ->orWhere('tickets.assignee_id', $viewer['id'])
            ->orWhere('tickets.resolver_id', $viewer['id']);

        $nip = (string) ($viewer['employee_no'] ?? '');

        if ($nip !== '') {
            $this->orGroupStart()->where('tickets.requester_nip', $nip)->where('tickets.is_requester_verified', 1)->groupEnd()
                ->orWhere('tickets.created_by', $nip);
        }

        if ($categoryIds !== []) {
            $this->orWhereIn('tickets.request_category_id', $categoryIds);
        }

        return $this->groupEnd();
    }

    /**
     * Tiket yang diajukan oleh atau untuk user ini.
     *
     * @param array{id: string, employee_no?: string} $viewer
     */
    public function onlyRequestedBy(array $viewer): static
    {
        $this->groupStart()->where('tickets.requester_id', $viewer['id']);

        $nip = (string) ($viewer['employee_no'] ?? '');

        if ($nip !== '') {
            $this->orGroupStart()->where('tickets.requester_nip', $nip)->where('tickets.is_requester_verified', 1)->groupEnd()
                ->orWhere('tickets.created_by', $nip);
        }

        return $this->groupEnd();
    }

    public function onlyAssignedTo(string $userId): static
    {
        return $this->groupStart()
            ->where('tickets.assignee_id', $userId)
            ->orWhere('tickets.resolver_id', $userId)
            ->groupEnd();
    }

    /**
     * Terbaru lebih dulu, dengan kategori dan PIC. Dipaginasi; pager ada di $this->pager.
     *
     * @return list<array>
     */
    public function paginateList(): array
    {
        return $this->selectWithNames()->orderBy('tickets.created_at', 'DESC')->paginate();
    }

    /**
     * Satu tiket lengkap dengan nama kategori, PIC, dan pengaju. Pasang scope (visibleTo) sebelum memanggil ini.
     */
    public function findDetail(string $id): ?array
    {
        return $this->selectWithNames()
            ->select('creator.name AS creator_name')
            ->join('users creator', 'creator.employee_no = tickets.created_by', 'left')
            ->where('tickets.id', $id)
            ->first();
    }

    private function selectWithNames(): static
    {
        return $this->select('tickets.*, category.name AS category_name, parent.name AS parent_name, '
                . 'assignee.name AS assignee_name, resolver.name AS resolver_name')
            ->join('request_categories category', 'category.id = tickets.request_category_id')
            ->join('request_categories parent', 'parent.id = category.parent_id', 'left')
            ->join('users assignee', 'assignee.id = tickets.assignee_id', 'left')
            ->join('users resolver', 'resolver.id = tickets.resolver_id', 'left');
    }
}
