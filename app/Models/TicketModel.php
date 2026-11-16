<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class TicketModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    public const CHANNEL_PORTAL = 'PORTAL';
    public const CHANNEL_PUBLIC_FORM = 'PUBLIC_FORM';

    public const NUMBER_PREFIX = 'HC';
    public const NUMBER_DIGITS = 6;

    protected $table = 'tickets';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $beforeInsert = ['generateUuid'];
    protected $allowedFields = [
        'ticket_number',
        'channel',
        'status',
        'requester_id',
        'requester_nip',
        'requester_name',
        'requester_phone',
        'requester_email',
        'requester_organization',
        'is_requester_verified',
        'assignee_id',
        'resolver_id',
        'request_category_id',
        'sla_policy_id',
        'priority',
        'subject',
        'description',
        'response_due_at',
        'resolution_due_at',
        'response_warned_at',
        'response_breached_at',
        'resolution_warned_at',
        'resolution_breached_at',
        'closed_at',
    ];

    protected $validationRules = [
        'ticket_number' => 'required|max_length[20]|is_unique[tickets.ticket_number,id,{id}]',
        'requester_id' => 'permit_empty|exact_length[36]',
        'request_category_id' => 'required|exact_length[36]',
        'subject' => 'required|max_length[150]',
        'description' => 'required',
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
     * Nomor tiket terbesar di tahun $year (format HC-YYYY-NNNNNN), atau null bila belum ada. Dipanggil di dalam
     * transaksi: FOR UPDATE mengunci baris itu sampai transaksi selesai, jadi pembuat tiket lain menunggu.
     */
    public function lastNumberInYear(string $year): ?string
    {
        $row = $this->db->query(
            'SELECT ticket_number FROM ' . $this->db->prefixTable($this->table)
            . ' WHERE ticket_number LIKE ? ORDER BY ticket_number DESC LIMIT 1 FOR UPDATE',
            [self::NUMBER_PREFIX . '-' . $year . '-%'],
        )->getRowArray();

        return $row['ticket_number'] ?? null;
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

    /**
     * Konfigurasi tiap jenis peringatan SLA: kolom batas, kolom penanda, dan status yang masih menghitung.
     * Respons dihitung selama tiket belum disentuh (OPEN); resolusi selama masih dikerjakan. NEED_RESPONSE dan ON_HOLD
     * dianggap jeda SLA, jadi tidak diperingatkan.
     */
    private const SLA_ALERTS = [
        'response_warning' => ['due' => 'response_due_at', 'flag' => 'response_warned_at', 'blocking' => 'response_breached_at', 'statuses' => ['OPEN'], 'breach' => false],
        'response_breached' => ['due' => 'response_due_at', 'flag' => 'response_breached_at', 'blocking' => null, 'statuses' => ['OPEN'], 'breach' => true],
        'resolution_warning' => ['due' => 'resolution_due_at', 'flag' => 'resolution_warned_at', 'blocking' => 'resolution_breached_at', 'statuses' => ['OPEN', 'IN_PROGRESS', 'ESCALATED', 'REOPENED'], 'breach' => false],
        'resolution_breached' => ['due' => 'resolution_due_at', 'flag' => 'resolution_breached_at', 'blocking' => null, 'statuses' => ['OPEN', 'IN_PROGRESS', 'ESCALATED', 'REOPENED'], 'breach' => true],
    ];

    /**
     * Tiket yang perlu peringatan SLA jenis $kind dan belum pernah diperingatkan: peringatan = batas jatuh dalam jendela
     * ($now, $windowEnd]; terlewat = batas lewat dalam rentang ($oldestDue, $now]. Batas yang lebih tua dari $oldestDue
     * diabaikan, jadi tiket lama yang sudah lama terlewat tidak membanjiri notifikasi saat pertama dijalankan.
     *
     * @return list<array>
     */
    public function dueForSla(string $kind, string $now, string $windowEnd, string $oldestDue, int $limit = 200): array
    {
        $alert = self::SLA_ALERTS[$kind];

        $this->whereIn('status', $alert['statuses'])->where($alert['flag'] . ' IS NULL', null, false);

        if ($alert['blocking'] !== null) {
            $this->where($alert['blocking'] . ' IS NULL', null, false);
        }

        if ($alert['breach']) {
            $this->where($alert['due'] . ' <=', $now)->where($alert['due'] . ' >', $oldestDue);
        } else {
            $this->where($alert['due'] . ' >', $now)->where($alert['due'] . ' <=', $windowEnd);
        }

        return $this->orderBy($alert['due'], 'ASC')->findAll($limit);
    }

    /**
     * Klaim atomik: hanya satu proses yang berhasil menandai peringatan sebuah tiket, jadi dua proses sla:check
     * yang berjalan bersamaan tidak membuat notifikasi ganda.
     */
    public function claimSla(string $id, string $kind, string $now): bool
    {
        $flag = self::SLA_ALERTS[$kind]['flag'];

        $this->builder()->where('id', $id)->where($flag . ' IS NULL', null, false)->update([$flag => $now]);

        return $this->db->affectedRows() === 1;
    }

    public function onlyAssignedTo(string $userId): static
    {
        return $this->groupStart()
            ->where('tickets.assignee_id', $userId)
            ->orWhere('tickets.resolver_id', $userId)
            ->groupEnd();
    }

    /**
     * Saring daftar. Semua kunci opsional dan digabung dengan AND:
     *  - keyword: cocok dengan nomor tiket, judul, atau nama pelapor
     *  - statuses, priorities: salah satu dari daftar
     *  - categories: kategori itu sendiri atau sub kategorinya
     *  - pics: sebagai PIC Helpdesk atau PIC Resolver
     *  - unassigned: belum punya PIC Helpdesk
     *  - tab: open | progress | expiring (all = tanpa saringan). expiring = tiket yang masih berjalan dengan prioritas
     *    urgent atau batas penyelesaiannya sudah lewat / jatuh sebelum expiring_before
     *
     * @param array{keyword?: string, statuses?: list<string>, categories?: list<string>, priorities?: list<string>,
     *     pics?: list<string>, unassigned?: bool, tab?: string, expiring_before?: string} $filters
     */
    public function matching(array $filters): static
    {
        $keyword = (string) ($filters['keyword'] ?? '');

        if ($keyword !== '') {
            $this->groupStart()
                ->like('tickets.ticket_number', $keyword)
                ->orLike('tickets.subject', $keyword)
                ->orLike('tickets.requester_name', $keyword)
                ->groupEnd();
        }

        if (!empty($filters['statuses'])) {
            $this->whereIn('tickets.status', $filters['statuses']);
        }

        if (!empty($filters['priorities'])) {
            $this->whereIn('tickets.priority', $filters['priorities']);
        }

        if (!empty($filters['categories'])) {
            $ids = $filters['categories'];

            $this->groupStart()
                ->whereIn('tickets.request_category_id', $ids)
                ->orWhereIn('tickets.request_category_id', static fn(BaseBuilder $sub) => $sub->select('id')->from('request_categories')->whereIn('parent_id', $ids))
                ->groupEnd();
        }

        if (!empty($filters['pics'])) {
            $this->groupStart()
                ->whereIn('tickets.assignee_id', $filters['pics'])
                ->orWhereIn('tickets.resolver_id', $filters['pics'])
                ->groupEnd();
        }

        if (!empty($filters['unassigned'])) {
            $this->where('tickets.assignee_id IS NULL', null, false);
        }

        return $this->matchingTab((string) ($filters['tab'] ?? 'all'), (string) ($filters['expiring_before'] ?? ''));
    }

    private function matchingTab(string $tab, string $expiringBefore): static
    {
        if ($tab === 'open') {
            return $this->where('tickets.status', 'OPEN');
        }

        if ($tab === 'progress') {
            return $this->where('tickets.status', 'IN_PROGRESS');
        }

        if ($tab !== 'expiring') {
            return $this;
        }

        return $this->whereNotIn('tickets.status', ['RESOLVED', 'CLOSED', 'CANCELLED'])
            ->groupStart()
            ->where('tickets.priority', 'urgent')
            ->orWhere('tickets.resolution_due_at <=', $expiringBefore)
            ->groupEnd();
    }

    /**
     * Satu kolom papan Kanban: jumlah seluruh tiket berstatus $statuses (pada saringan yang sudah dipasang) dan
     * $limit tiket terbaru. Pasang scope dan matching() sebelum memanggil ini.
     *
     * @param list<string> $statuses
     *
     * @return array{total: int, tickets: list<array>}
     */
    public function boardColumn(array $statuses, int $limit): array
    {
        $this->whereIn('tickets.status', $statuses);

        // false = jangan reset builder; saringan yang sama dipakai lagi untuk mengambil kartunya
        $total = $this->countAllResults(false);

        return ['total' => $total, 'tickets' => $this->selectWithNames()->orderBy('tickets.created_at', 'DESC')->findAll($limit)];
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
     * Beberapa tiket terbaru dengan kategori dan PIC, tanpa paginasi.
     *
     * @return list<array>
     */
    public function latest(int $limit): array
    {
        return $this->selectWithNames()->orderBy('tickets.created_at', 'DESC')->findAll($limit);
    }

    /**
     * Jumlah tiket per status. Pasang scope (visibleTo) sebelum memanggil ini.
     *
     * @return array<string, int> status => jumlah
     */
    public function statusCounts(): array
    {
        $rows = $this->select('tickets.status, COUNT(*) AS total')->groupBy('tickets.status')->findAll();

        return array_map('intval', array_column($rows, 'total', 'status'));
    }

    /**
     * Pencarian dan filter daftar tiket; nilai kosong diabaikan. Pasang sebelum latest()/paginateList().
     *
     * @param list<string> $statuses
     * @param list<string> $categoryIds kategori utama; tiket di sub kategorinya ikut tercakup
     */
    public function filterBy(string $keyword, array $statuses, array $categoryIds): static
    {
        if ($keyword !== '') {
            $this->groupStart()
                ->like('tickets.ticket_number', $keyword)
                ->orLike('tickets.subject', $keyword)
                ->orLike('assignee.name', $keyword)
                ->groupEnd();
        }

        if ($statuses !== []) {
            $this->whereIn('tickets.status', $statuses);
        }

        if ($categoryIds !== []) {
            $this->groupStart()
                ->whereIn('tickets.request_category_id', $categoryIds)
                ->orWhereIn('category.parent_id', $categoryIds)
                ->groupEnd();
        }

        return $this;
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
            . 'assignee.name AS assignee_name, assignee.job_title AS assignee_job_title, resolver.name AS resolver_name, '
            . 'sla.resolution_minutes AS sla_limit_minutes, sla.is_holiday AS sla_is_holiday, '
            . 'sla.is_off_day AS sla_is_off_day, sla.is_work_hours AS sla_is_work_hours')
            ->join('request_categories category', 'category.id = tickets.request_category_id')
            ->join('sla_policies sla', 'sla.id = tickets.sla_policy_id', 'left')
            ->join('request_categories parent', 'parent.id = category.parent_id', 'left')
            ->join('users assignee', 'assignee.id = tickets.assignee_id', 'left')
            ->join('users resolver', 'resolver.id = tickets.resolver_id', 'left');
    }
}
