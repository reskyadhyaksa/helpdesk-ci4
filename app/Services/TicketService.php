<?php

namespace App\Services;

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;
use App\Exceptions\BusinessException;
use App\Models\PermissionModel;
use App\Models\RequestCategoryModel;
use App\Models\SlaPolicyModel;
use App\Models\TicketActivityModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Services\Auth\KeycloakAuthService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class TicketService
{
    public const SCOPE_MINE     = 'mine';
    public const SCOPE_ASSIGNED = 'assigned';

    // Tab cepat di daftar tiket; "expiring" = tiket berjalan yang urgent atau batas penyelesaiannya habis dalam jendela ini
    public const TABS = ['all', 'open', 'progress', 'expiring'];

    private const EXPIRING_WINDOW_MINUTES = 60;

    // Kartu per kolom di papan Kanban; selebihnya dilihat di tampilan tabel
    public const BOARD_LIMIT = 20;

    private const NO_NOTIFICATIONS = ['outbox' => [], 'inApp' => []];

    // Peran PIC → kolom tiket dan nama aktivitas
    private const PIC_ROLES = [
        'assignee' => ['column' => 'assignee_id', 'action' => TicketActivityModel::ACTION_ASSIGNEE_CHANGED],
        'resolver' => ['column' => 'resolver_id', 'action' => TicketActivityModel::ACTION_RESOLVER_CHANGED],
    ];

    public function __construct(
        private readonly TicketModel $tickets,
        private readonly TicketActivityModel $activities,
        private readonly RequestCategoryModel $categories,
        private readonly SlaPolicyModel $policies,
        private readonly HolidayService $holidays,
        private readonly SlaCalculator $calculator,
        private readonly CategoryAccessService $access,
        private readonly UserModel $users,
        private readonly KeycloakAuthService $hr,
        private readonly AttachmentService $attachments,
        private readonly TicketNotifier $notifications,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<array> kategori yang bisa dipilih untuk tiket baru
     */
    public function categoryOptions(): array
    {
        return $this->categories->selectableForTicket();
    }

    /**
     * Tiket dari user yang login. Bila $data['for_nip'] diisi dengan NIK lain, tiket diajukan atas nama orang itu:
     * pemohon = NIK tersebut, pengaju = $actor. Keduanya melihat tiketnya.
     *
     * @param array{category_id: string, subject: string, description: string, for_nip?: string} $data
     * @param array{id: string, employee_no: string}                                           $actor
     *
     * @throws BusinessException
     */
    public function create(array $data, array $actor, array $files = []): array
    {
        $requester = $this->requesterFor($actor, trim((string) ($data['for_nip'] ?? '')));

        return $this->store($data, $requester, TicketModel::CHANNEL_PORTAL, $actor['id'], $files);
    }

    /**
     * Tiket dari pengunjung tanpa login. Data HR (dicari lewat NIK) dicocokkan dengan isian pengunjung;
     * hasilnya hanya menandai tiket, tidak menolaknya.
     *
     * @param array{nip: string, phone: string, email: string, organization: string, category_id: string, subject: string, description: string} $data
     *
     * @throws BusinessException
     */
    public function createPublic(array $data, array $files = []): array
    {
        $nip      = trim($data['nip']);
        $employee = $this->hr->getHelpdeskUserData($nip);

        $requester = [
            'requester_id'           => null,
            'requester_nip'          => $nip,
            'requester_name'         => $employee['name'] ?? $employee['fullName'] ?? $nip,
            'requester_phone'        => trim($data['phone']),
            'requester_email'        => trim($data['email']) ?: null,
            'requester_organization' => ($employee['organization'] ?? null) ?: (trim($data['organization']) ?: null),
            'is_requester_verified'  => $this->matchesHr($employee, $data) ? 1 : 0,
        ];

        return $this->store($data, $requester, TicketModel::CHANNEL_PUBLIC_FORM, null, $files);
    }

    /**
     * @throws BusinessException
     */
    public function changeStatus(string $ticketId, TicketStatus $to, ?string $actorId): void
    {
        $ticket = $this->tickets->find($ticketId) ?? throw new BusinessException(lang('Ticket.notFound'));
        $from   = TicketStatus::from($ticket['status']);

        if (! $from->canTransitionTo($to)) {
            throw new BusinessException(lang('Ticket.illegalTransition', [$from->label(), $to->label()]));
        }

        $pending = self::NO_NOTIFICATIONS;

        $this->inTransaction(function () use ($ticket, $ticketId, $from, $to, $actorId, &$pending): void {
            // closed_at dipakai kebijakan retensi lampiran (hapus N hari setelah CLOSED)
            $changes = ['status' => $to->value] + ($to === TicketStatus::Closed ? ['closed_at' => date('Y-m-d H:i:s')] : []);

            $this->tickets->update($ticketId, $changes);
            $this->activities->record($ticketId, $actorId, TicketActivityModel::ACTION_STATUS_CHANGED, $from->value, $to->value);

            $pending = $this->queueNotifications(fn (): array => $this->notifications->forStatusChange($ticket, $from, $to, $actorId));
        });

        $this->notifications->dispatch($pending);
    }

    /**
     * @param 'assignee'|'resolver' $role   PIC Helpdesk atau PIC Resolver
     * @param string|null           $userId null = kosongkan PIC
     *
     * @throws BusinessException
     */
    public function assign(string $ticketId, string $role, ?string $userId, ?string $actorId): void
    {
        $config    = self::PIC_ROLES[$role] ?? throw new BusinessException(lang('Ticket.picInvalid'));
        $ticket    = $this->tickets->find($ticketId) ?? throw new BusinessException(lang('Ticket.notFound'));
        $newName   = $userId === null ? null : $this->candidateName($ticket, $userId);
        $oldUserId = $ticket[$config['column']] ?? null;
        $oldName   = $oldUserId === null ? null : $this->users->find($oldUserId)?->name;

        $pending = self::NO_NOTIFICATIONS;

        $this->inTransaction(function () use ($ticket, $ticketId, $role, $config, $userId, $oldUserId, $actorId, $oldName, $newName, &$pending): void {
            $this->tickets->update($ticketId, [$config['column'] => $userId]);
            $this->activities->record($ticketId, $actorId, $config['action'], $oldName, $newName);

            // Hanya PIC yang benar-benar baru yang diberi tahu
            if ($userId !== null && $userId !== $oldUserId) {
                $pending = $this->queueNotifications(fn (): array => $this->notifications->forAssignment($ticket, $role, $userId, $actorId));
            }
        });

        $this->notifications->dispatch($pending);
    }

    /**
     * Menulis outbox di transaksi perubahan tiket. Gagal menyusun notifikasi hanya dicatat di log:
     * perubahan tiket tidak boleh batal karena notifikasi.
     *
     * @param callable(): array{outbox: list<string>, inApp: list<array>} $queue
     *
     * @return array{outbox: list<string>, inApp: list<array>} notifikasi yang menunggu dispatch setelah commit
     */
    private function queueNotifications(callable $queue): array
    {
        try {
            return $queue();
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi tiket gagal disusun: ' . $e->getMessage());

            return self::NO_NOTIFICATIONS;
        }
    }

    /**
     * Kandidat PIC untuk satu tiket: pengguna dengan akses panel admin yang juga boleh mengakses tiket itu,
     * yaitu punya permission lihat-semua atau kategori tiketnya ada di akses kategorinya.
     *
     * @return list<array{id: string, name: string, employee_no: string}>
     */
    public function picCandidates(array $ticket): array
    {
        $viewAllIds = array_column($this->users->withPermission(PermissionModel::ADMIN_TICKETS_VIEW_ALL), 'id');
        $categoryId = (string) ($ticket['request_category_id'] ?? '');

        return array_values(array_filter(
            $this->users->withPermission(PermissionModel::ADMIN_ACCESS),
            fn (array $candidate): bool => in_array($candidate['id'], $viewAllIds, true)
                || in_array($categoryId, $this->categoryIdsFor($candidate['id']), true),
        ));
    }

    /**
     * Satu tiket bila terlihat oleh $viewer, selain itu null.
     *
     * @param array{id: string, employee_no?: string} $viewer
     */
    public function find(string $ticketId, array $viewer, bool $canViewAll): ?array
    {
        $this->tickets->visibleTo($viewer, $this->categoryIdsFor($viewer['id']), $canViewAll);

        return $this->tickets->findDetail($ticketId);
    }

    /**
     * @return list<array>
     */
    public function activities(string $ticketId): array
    {
        return $this->activities->forTicket($ticketId);
    }

    /**
     * Saringan daftar tiket dari query string (q, status[], category[], priority[], pic[], unassigned, tab) → nilai yang
     * aman dan sudah dibersihkan; nilai di luar pilihan dibuang.
     *
     * @param array<string, mixed> $input
     *
     * @return array{keyword: string, statuses: list<string>, categories: list<string>, priorities: list<string>, pics: list<string>, unassigned: bool, tab: string, view: 'table'|'kanban'}
     */
    public function normalizeFilters(array $input): array
    {
        $values = static fn (mixed $value): array => array_values(array_filter(array_map('strval', (array) $value), static fn (string $item): bool => $item !== ''));
        $ids    = static fn (array $list): array => array_values(array_filter($list, static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/i', $id) === 1));

        return [
            'keyword'    => trim((string) ($input['q'] ?? '')),
            'statuses'   => array_values(array_intersect(array_keys(TicketStatus::options()), $values($input['status'] ?? []))),
            'categories' => $ids($values($input['category'] ?? [])),
            'priorities' => array_values(array_intersect(array_keys(SlaPolicy::PRIORITY_LABELS), $values($input['priority'] ?? []))),
            'pics'       => $ids($values($input['pic'] ?? [])),
            'unassigned' => ($input['unassigned'] ?? '') === '1',
            'tab'        => in_array($input['tab'] ?? '', self::TABS, true) ? $input['tab'] : 'all',
            'view'       => ($input['view'] ?? '') === 'kanban' ? 'kanban' : 'table',
        ];
    }

    /**
     * Pilihan dropdown filter daftar tiket: nilai => label per saringan.
     *
     * @return array{status: array<string, string>, category: array<string, string>, priority: array<string, string>, pic: array<string, string>}
     */
    public function filterOptions(): array
    {
        $names    = array_column($this->categories->allOrdered(), 'name', 'id');
        $category = [];

        foreach ($this->categories->allOrdered() as $row) {
            $category[$row['id']] = $row['parent_id'] === null ? $row['name'] : ($names[$row['parent_id']] ?? '') . ' › ' . $row['name'];
        }

        return [
            'status'   => TicketStatus::options(),
            'category' => $category,
            'priority' => SlaPolicy::PRIORITY_LABELS,
            'pic'      => array_column($this->users->withPermission(PermissionModel::ADMIN_ACCESS), 'name', 'id'),
        ];
    }

    /**
     * @param array{id: string, employee_no?: string} $viewer
     * @param self::SCOPE_*|null                      $scope   null = semua yang terlihat
     * @param array<string, mixed>                    $filters hasil normalizeFilters(); kosong = tanpa saringan
     *
     * @return array{tickets: list<array>, pager: Pager}
     */
    public function paginateVisible(array $viewer, bool $canViewAll, ?string $scope = null, array $filters = []): array
    {
        $this->scoped($viewer, $canViewAll, $scope)->matching($this->withExpiringBefore($filters));

        return ['tickets' => $this->withSlaRemaining($this->tickets->paginateList()), 'pager' => $this->tickets->pager];
    }

    /**
     * Papan Kanban: satu kolom per TicketStatus::boardColumns(), masing-masing berisi jumlah total dan
     * BOARD_LIMIT tiket terbaru (lengkap dengan sisa SLA). Cakupan dan saringan sama dengan daftar tabel.
     *
     * @param array<string, mixed> $filters hasil normalizeFilters()
     *
     * @return list<array{key: string, label: string, tone: string, statuses: list<string>, total: int, tickets: list<array>}>
     */
    public function board(array $viewer, bool $canViewAll, ?string $scope, array $filters): array
    {
        $columns = [];

        foreach (TicketStatus::boardColumns() as $key => $column) {
            $this->scoped($viewer, $canViewAll, $scope)->matching($this->withExpiringBefore($filters));

            $result    = $this->tickets->boardColumn($column['statuses'], self::BOARD_LIMIT);
            $columns[] = ['key' => $key, 'total' => $result['total'], 'tickets' => $this->withSlaRemaining($result['tickets'])] + $column;
        }

        return $columns;
    }

    /**
     * Jumlah tiket per tab (all, open, progress, expiring) pada cakupan dan saringan yang sama dengan daftar,
     * kecuali tab itu sendiri, jadi angkanya sama dengan isi daftar bila tab diklik.
     *
     * @param array<string, mixed> $filters hasil normalizeFilters()
     *
     * @return array<string, int>
     */
    public function tabCounts(array $viewer, bool $canViewAll, ?string $scope, array $filters): array
    {
        $counts = [];

        foreach (self::TABS as $tab) {
            $this->scoped($viewer, $canViewAll, $scope)->matching($this->withExpiringBefore(['tab' => $tab] + $filters));

            $counts[$tab] = $this->tickets->countAllResults();
        }

        return $counts;
    }

    private function scoped(array $viewer, bool $canViewAll, ?string $scope): TicketModel
    {
        $this->tickets->visibleTo($viewer, $this->categoryIdsFor($viewer['id']), $canViewAll);

        if ($scope === self::SCOPE_MINE) {
            $this->tickets->onlyRequestedBy($viewer);
        }

        if ($scope === self::SCOPE_ASSIGNED) {
            $this->tickets->onlyAssignedTo($viewer['id']);
        }

        return $this->tickets;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function withExpiringBefore(array $filters): array
    {
        $before = new DateTimeImmutable('now +' . self::EXPIRING_WINDOW_MINUTES . ' minutes', new DateTimeZone(app_timezone()));

        return $filters + ['expiring_before' => $before->format('Y-m-d H:i:s')];
    }

    /**
     * Menambah sla_remaining_minutes: sisa menit SLA ke batas penyelesaian, dihitung dengan aturan kebijakan tiket
     * itu (lewati hari libur, hari off, hanya jam kerja), sama dengan perhitungan batasnya. Negatif = terlambat.
     * Null untuk tiket tanpa SLA atau yang sudah selesai.
     *
     * @param list<array> $tickets
     *
     * @return list<array>
     */
    public function withSlaRemaining(array $tickets): array
    {
        $now   = new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
        $dues  = [];
        $dates = [$now->format('Y-m-d')];

        foreach ($tickets as $i => $ticket) {
            $status = TicketStatus::from($ticket['status']);

            if ($ticket['resolution_due_at'] !== null && $ticket['sla_limit_minutes'] !== null && $status !== TicketStatus::Resolved && ! $status->isFinal()) {
                $dues[$i] = new DateTimeImmutable($ticket['resolution_due_at'], new DateTimeZone(app_timezone()));
                $dates[]  = $dues[$i]->format('Y-m-d');
            }
        }

        if ($dues === []) {
            return array_map(static fn (array $ticket): array => $ticket + ['sla_remaining_minutes' => null], $tickets);
        }

        $schedule     = $this->holidays->schedule();
        $holidayDates = $this->holidays->holidayDatesFrom(min($dates));

        foreach ($tickets as $i => $ticket) {
            $policy = new SlaPolicy([
                'is_holiday'    => $ticket['sla_is_holiday'],
                'is_off_day'    => $ticket['sla_is_off_day'],
                'is_work_hours' => $ticket['sla_is_work_hours'],
            ]);

            $tickets[$i]['sla_remaining_minutes'] = isset($dues[$i])
                ? $this->calculator->workingMinutesBetween($now, $dues[$i], $policy, $schedule, $holidayDates)
                : null;
        }

        return $tickets;
    }

    /**
     * @return list<string>
     */
    private function categoryIdsFor(string $userId): array
    {
        // Akses kategori dihitung dari user yang dimuat segar, bukan salinan session (kontrak CategoryAccessService)
        return $this->access->effectiveCategoryIds($this->users->find($userId));
    }

    private function candidateName(array $ticket, string $userId): string
    {
        foreach ($this->picCandidates($ticket) as $candidate) {
            if ($candidate['id'] === $userId) {
                return $candidate['name'];
            }
        }

        throw new BusinessException(lang('Ticket.picInvalid'));
    }

    /**
     * @throws BusinessException
     */
    private function requesterFor(array $actor, string $forNip): array
    {
        if ($forNip === '' || $forNip === $actor['employee_no']) {
            return $this->fromUser($this->users->find($actor['id']), withContact: true);
        }

        // Atas nama orang lain: hanya identitas (NIK dan nama) yang disimpan. Data kontak HR orang itu tidak boleh
        // sampai ke pengaju, yang ikut melihat tiketnya.
        $user = $this->users->findByEmployeeNo($forNip);

        if ($user !== null) {
            return $this->fromUser($user, withContact: false);
        }

        $employee = $this->hr->getHelpdeskUserData($forNip) ?? throw new BusinessException(lang('Ticket.requesterNotFound'));

        return [
            'requester_id'           => null,
            'requester_nip'          => $forNip,
            'requester_name'         => $employee['name'] ?? $employee['fullName'] ?? $forNip,
            'requester_phone'        => null,
            'requester_email'        => null,
            'requester_organization' => null,
            'is_requester_verified'  => 1,
        ];
    }

    private function fromUser(object $user, bool $withContact): array
    {
        return [
            'requester_id'           => $user->id,
            'requester_nip'          => $user->employee_no,
            'requester_name'         => $user->name,
            'requester_phone'        => null,
            'requester_email'        => $withContact ? $user->email : null,
            'requester_organization' => $withContact ? $user->organization : null,
            'is_requester_verified'  => 1,
        ];
    }

    /**
     * Cocok bila NIK dikenal HR, telepon atau email berhasil dibandingkan, dan tidak ada data yang berbeda.
     * Divisi saja tidak cukup (mudah ditebak). Data yang tidak dimiliki HR dilewati (mis. OS tanpa email perusahaan).
     */
    private function matchesHr(?array $employee, array $data): bool
    {
        if ($employee === null) {
            return false;
        }

        $contact = [
            $this->compare($this->phoneOf($employee), $data['phone'], $this->normalizePhone(...)),
            $this->compare($employee['officeMail'] ?? null, $data['email'] ?? '', $this->normalizeText(...)),
        ];
        $organization = $this->compare($employee['organization'] ?? null, $data['organization'] ?? '', $this->normalizeText(...));

        $compared = array_filter([...$contact, $organization], static fn (?bool $r): bool => $r !== null);

        return array_filter($contact, static fn (?bool $r): bool => $r !== null) !== [] && ! in_array(false, $compared, true);
    }

    /**
     * @return bool|null null = HR tidak punya data ini
     */
    private function compare(?string $hrValue, string $submitted, callable $normalize): ?bool
    {
        if ($hrValue === null || trim($hrValue) === '') {
            return null;
        }

        return $normalize($hrValue) === $normalize($submitted);
    }

    private function phoneOf(array $employee): ?string
    {
        return $employee['phone'] ?? $employee['phoneNumber'] ?? $employee['mobilePhone'] ?? null;
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @throws BusinessException
     */
    private function store(array $data, array $requester, string $channel, ?string $actorId, array $files): array
    {
        $category = $this->categories->find($data['category_id']);

        if ($category === null || ! (int) $category['is_active'] || ! $this->parentIsActive($category)) {
            throw new BusinessException(lang('Ticket.categoryInvalid'));
        }

        if ($this->categories->hasChildren($category['id'])) {
            throw new BusinessException(lang('Ticket.categoryHasSub'));
        }

        // Lampiran divalidasi sebelum apa pun disimpan, supaya file yang ditolak tidak meninggalkan tiket setengah jadi
        $files = $this->attachments->validateAll($files);

        $policy = $this->activePolicy($this->effectiveSlaPolicyId($category));
        $now    = new DateTimeImmutable('now', new DateTimeZone(app_timezone()));

        $row = $requester + [
            'channel'           => $channel,
            'status'              => TicketStatus::Open->value,
            'request_category_id' => $category['id'],
            'sla_policy_id'       => $policy?->id,
            'priority'            => $policy?->priority,
            'subject'             => trim($data['subject']),
            'description'         => trim($data['description']),
            'response_due_at'     => $policy === null ? null : $this->dueAt($now, $policy->response_minutes, $policy),
            'resolution_due_at'   => $policy === null ? null : $this->dueAt($now, $policy->resolution_minutes, $policy),
        ];

        $id     = null;
        $stored = [];

        try {
            $this->inTransaction(function () use ($row, $now, $actorId, $files, &$id, &$stored): void {
                // Nomor diambil di dalam transaksi: baris terakhir terkunci, jadi dua tiket bersamaan tidak berebut nomor
                $row['ticket_number'] = $this->newTicketNumber($now);
                $id = $this->tickets->insert($row, true);

                if ($id === false) {
                    throw new BusinessException(implode(' ', $this->tickets->errors()));
                }

                $this->activities->record($id, $actorId, TicketActivityModel::ACTION_CREATED);

                foreach ($files as $file) {
                    $stored[] = $this->attachments->store($id, null, $file);
                }
            });
        } catch (Throwable $e) {
            // Baris database sudah dibatalkan; file yang terlanjur tersimpan ikut dibuang
            $this->attachments->discard($stored);

            throw $e;
        }

        return $this->tickets->find($id);
    }

    /**
     * SLA kategori itu sendiri; bila sub kategori tidak memilih SLA, SLA kategori utamanya.
     */
    private function effectiveSlaPolicyId(array $category): ?string
    {
        if (! empty($category['sla_policy_id'])) {
            return $category['sla_policy_id'];
        }

        if (($category['parent_id'] ?? null) === null) {
            return null;
        }

        return $this->categories->find($category['parent_id'])['sla_policy_id'] ?? null;
    }

    /**
     * Menonaktifkan kategori utama menutup seluruh cabangnya.
     */
    private function parentIsActive(array $category): bool
    {
        if (($category['parent_id'] ?? null) === null) {
            return true;
        }

        $parent = $this->categories->find($category['parent_id']);

        return $parent !== null && (int) $parent['is_active'] === 1;
    }

    private function activePolicy(?string $policyId): ?SlaPolicy
    {
        if ($policyId === null) {
            return null;
        }

        $policy = $this->policies->find($policyId);

        return $policy !== null && $policy->is_active ? $policy : null;
    }

    private function dueAt(DateTimeImmutable $start, int $minutes, SlaPolicy $policy): string
    {
        $due = $this->calculator->dueAt(
            $start,
            $minutes,
            $policy,
            $this->holidays->schedule(),
            $this->holidays->holidayDatesFrom($start->format('Y-m-d')),
        );

        return $due->format('Y-m-d H:i:s');
    }

    /**
     * HC-YYYY-NNNNNN: nomor urut per tahun, mulai 000001 tiap tahun baru.
     */
    private function newTicketNumber(DateTimeImmutable $now): string
    {
        $year = $now->format('Y');
        $last = $this->tickets->lastNumberInYear($year);
        $next = $last === null ? 1 : (int) substr($last, -TicketModel::NUMBER_DIGITS) + 1;

        return sprintf('%s-%s-%0' . TicketModel::NUMBER_DIGITS . 'd', TicketModel::NUMBER_PREFIX, $year, $next);
    }

    /**
     * Tiket dan riwayatnya ditulis di dua tabel, jadi keduanya berhasil atau batal bersama.
     */
    private function inTransaction(callable $work): void
    {
        $this->db->transBegin();

        try {
            $work();
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
}
