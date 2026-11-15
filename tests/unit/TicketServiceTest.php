<?php

use App\Entities\SlaPolicy;
use App\Entities\TicketStatus;
use App\Entities\User;
use App\Exceptions\BusinessException;
use App\Models\RequestCategoryModel;
use App\Models\SlaPolicyModel;
use App\Models\TicketActivityModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Services\Auth\KeycloakAuthService;
use App\Services\AttachmentService;
use App\Services\CategoryAccessService;
use App\Services\HolidayService;
use App\Services\SlaCalculator;
use App\Services\TicketService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TicketServiceTest extends CIUnitTestCase
{
    private const CATEGORY_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';
    private const POLICY_ID   = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0c';

    private TicketModel $tickets;
    private TicketActivityModel $activities;
    private RequestCategoryModel $categories;
    private SlaPolicyModel $policies;
    private SlaCalculator $calculator;
    private CategoryAccessService $access;
    private UserModel $users;
    private KeycloakAuthService $hr;
    private AttachmentService $attachmentService;

    /** @var list<array> baris yang di-insert ke tickets */
    private array $inserted = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tickets    = $this->createMock(TicketModel::class);
        $this->activities = $this->createMock(TicketActivityModel::class);
        $this->categories = $this->createMock(RequestCategoryModel::class);
        $this->policies   = $this->createMock(SlaPolicyModel::class);
        $this->calculator = $this->createMock(SlaCalculator::class);
        $this->access     = $this->createMock(CategoryAccessService::class);
        $this->users      = $this->createMock(UserModel::class);
        $this->hr         = $this->createMock(KeycloakAuthService::class);
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->attachmentService->method('validateAll')->willReturnArgument(0);

        $this->tickets->method('insert')->willReturnCallback(function (array $row) {
            $this->inserted[] = $row;

            return 't1';
        });
        $this->tickets->method('find')->willReturn(['id' => 't1', 'status' => 'OPEN']);
    }

    private function service(): TicketService
    {
        $holidays = $this->createMock(HolidayService::class);
        $holidays->method('schedule')->willReturn([]);
        $holidays->method('holidayDatesFrom')->willReturn([]);

        return new TicketService(
            $this->tickets,
            $this->activities,
            $this->categories,
            $this->policies,
            $holidays,
            $this->calculator,
            $this->access,
            $this->users,
            $this->hr,
            $this->attachmentService,
            $this->createMock(BaseConnection::class),
        );
    }

    private function actor(): array
    {
        return ['id' => 'u1', 'employee_no' => '00001'];
    }

    private function actorUser(): User
    {
        return new User(['id' => 'u1', 'employee_no' => '00001', 'name' => 'Budi', 'email' => 'budi@x.id', 'organization' => 'Human Capital']);
    }

    private function data(array $extra = []): array
    {
        return $extra + ['category_id' => self::CATEGORY_ID, 'subject' => '  Akun terkunci ', 'description' => ' Tidak bisa login '];
    }

    private function category(array $overrides = []): array
    {
        return array_merge(['id' => self::CATEGORY_ID, 'is_active' => '1', 'sla_policy_id' => null, 'parent_id' => null], $overrides);
    }

    private function policy(bool $active = true): SlaPolicy
    {
        return new SlaPolicy([
            'id' => self::POLICY_ID, 'priority' => SlaPolicy::PRIORITY_URGENT,
            'response_minutes' => 60, 'resolution_minutes' => 240, 'is_active' => $active,
        ]);
    }

    private function createAsSelf(array $data = []): array
    {
        $this->users->method('find')->willReturn($this->actorUser());

        return $this->service()->create($data ?: $this->data(), $this->actor());
    }

    // --- Kategori dan SLA -------------------------------------------------

    public function testCreateRejectsUnknownCategory(): void
    {
        $this->categories->method('find')->willReturn(null);
        $this->expectException(BusinessException::class);

        $this->createAsSelf();
    }

    public function testCreateRejectsInactiveCategory(): void
    {
        $this->categories->method('find')->willReturn($this->category(['is_active' => '0']));
        $this->expectException(BusinessException::class);

        $this->createAsSelf();
    }

    public function testCreateRejectsCategoryWhoseParentIsInactive(): void
    {
        $this->categories->method('find')->willReturnMap([
            [self::CATEGORY_ID, $this->category(['parent_id' => 'p1'])],
            ['p1', ['id' => 'p1', 'is_active' => '0', 'parent_id' => null]],
        ]);
        $this->expectException(BusinessException::class);

        $this->createAsSelf();
    }

    public function testCreateRejectsCategoryWithSubCategories(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->categories->method('hasChildren')->willReturn(true);
        $this->expectException(BusinessException::class);

        $this->createAsSelf();
    }

    public function testCreateWithoutSlaStoresNullDueDates(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->calculator->expects($this->never())->method('dueAt');

        $this->createAsSelf();

        $this->assertNull($this->inserted[0]['response_due_at']);
        $this->assertNull($this->inserted[0]['sla_policy_id']);
    }

    public function testCreateIgnoresInactivePolicy(): void
    {
        $this->categories->method('find')->willReturn($this->category(['sla_policy_id' => self::POLICY_ID]));
        $this->policies->method('find')->willReturn($this->policy(active: false));
        $this->calculator->expects($this->never())->method('dueAt');

        $this->createAsSelf();

        $this->assertNull($this->inserted[0]['response_due_at']);
    }

    public function testCreateComputesDueDatesFromCalculator(): void
    {
        $this->categories->method('find')->willReturn($this->category(['sla_policy_id' => self::POLICY_ID]));
        $this->policies->method('find')->willReturn($this->policy());
        $this->calculator->method('dueAt')->willReturnCallback(
            static fn ($start, int $minutes) => new DateTimeImmutable($minutes === 60 ? '2026-11-16 09:30:00' : '2026-11-17 09:30:00'),
        );

        $this->createAsSelf();

        $row = $this->inserted[0];
        $this->assertSame('2026-11-16 09:30:00', $row['response_due_at']);
        $this->assertSame('2026-11-17 09:30:00', $row['resolution_due_at']);
        $this->assertSame(self::POLICY_ID, $row['sla_policy_id']);
        $this->assertSame(SlaPolicy::PRIORITY_URGENT, $row['priority']);
        $this->assertSame('Akun terkunci', $row['subject']);
        $this->assertSame(1, preg_match('/^TKT-\d{6}-[0-9A-F]{6}$/', $row['ticket_number']));
    }

    // --- Pemohon: diri sendiri dan atas nama orang lain -------------------

    public function testCreateForSelfStoresRequesterSnapshotAndOpenStatus(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->activities->expects($this->once())->method('record')->with('t1', 'u1', 'created');

        $this->createAsSelf();

        $row = $this->inserted[0];
        $this->assertSame('PORTAL', $row['channel']);
        $this->assertSame('OPEN', $row['status']);
        $this->assertSame('u1', $row['requester_id']);
        $this->assertSame('00001', $row['requester_nip']);
        $this->assertSame('Budi', $row['requester_name']);
        $this->assertSame(1, $row['is_requester_verified']);
    }

    public function testCreateOnBehalfOfUnknownUserUsesHrDataAndKeepsActor(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('findByEmployeeNo')->with('00002')->willReturn(null);
        $this->hr->method('getHelpdeskUserData')->with('00002')->willReturn(['name' => 'Sari', 'officeMail' => 's@x.id', 'phone' => '6281', 'organization' => 'HC Regional']);
        $this->activities->expects($this->once())->method('record')->with('t1', 'u1', 'created');

        $this->service()->create($this->data(['for_nip' => '00002']), $this->actor());

        $row = $this->inserted[0];
        $this->assertNull($row['requester_id']);
        $this->assertSame('00002', $row['requester_nip']);
        $this->assertSame('Sari', $row['requester_name']);
        $this->assertSame('PORTAL', $row['channel']);
        // Pengaju tidak boleh membaca data kontak HR orang lain
        $this->assertNull($row['requester_phone']);
        $this->assertNull($row['requester_email']);
        $this->assertNull($row['requester_organization']);
    }

    public function testCreateOnBehalfOfKnownUserLinksRequesterId(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('findByEmployeeNo')->willReturn(new User(['id' => 'u2', 'employee_no' => '00002', 'name' => 'Sari', 'email' => 's@x.id', 'organization' => 'HC']));
        $this->hr->expects($this->never())->method('getHelpdeskUserData');

        $this->service()->create($this->data(['for_nip' => '00002']), $this->actor());

        $this->assertSame('u2', $this->inserted[0]['requester_id']);
        $this->assertNull($this->inserted[0]['requester_email']);
        $this->assertNull($this->inserted[0]['requester_organization']);
    }

    public function testCreateOnBehalfRejectsNipUnknownEverywhere(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('findByEmployeeNo')->willReturn(null);
        $this->hr->method('getHelpdeskUserData')->willReturn(null);
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(['for_nip' => '99999']), $this->actor());
    }

    // --- Tiket tanpa login -------------------------------------------------

    private function publicData(array $overrides = []): array
    {
        return $overrides + $this->data() + [
            'nip' => '00002', 'phone' => '0812-3456-789', 'email' => 'Sari@X.id', 'organization' => 'hc regional',
        ];
    }

    public function testCreatePublicIsVerifiedWhenAllHrFieldsMatch(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn([
            'name' => 'Sari', 'officeMail' => 's@x.id', 'phone' => '628123456789', 'organization' => 'HC Regional',
        ]);
        $this->activities->expects($this->once())->method('record')->with('t1', null, 'created');

        $this->service()->createPublic($this->publicData(['email' => 'S@X.id']));

        $row = $this->inserted[0];
        $this->assertSame('PUBLIC_FORM', $row['channel']);
        $this->assertSame(1, $row['is_requester_verified']);
        $this->assertSame('Sari', $row['requester_name']);
        $this->assertSame('0812-3456-789', $row['requester_phone']);
    }

    public function testCreatePublicIsUnverifiedWhenPhoneMismatch(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(['name' => 'Sari', 'phone' => '6281111111111', 'organization' => 'HC Regional']);

        $this->service()->createPublic($this->publicData());

        $this->assertSame(0, $this->inserted[0]['is_requester_verified']);
    }

    public function testCreatePublicIsUnverifiedWhenNipUnknown(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(null);

        $this->service()->createPublic($this->publicData());

        $row = $this->inserted[0];
        $this->assertSame(0, $row['is_requester_verified']);
        $this->assertSame('00002', $row['requester_name']);
    }

    public function testCreatePublicIgnoresFieldsHrDoesNotHave(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(['name' => 'Sari', 'phone' => '628123456789']);

        $this->service()->createPublic($this->publicData(['email' => 'pribadi@gmail.com', 'organization' => 'apa saja']));

        $this->assertSame(1, $this->inserted[0]['is_requester_verified']);
    }

    public function testCreatePublicIsUnverifiedWhenOnlyOrganizationMatches(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(['name' => 'Sari', 'organization' => 'HC Regional']);

        $this->service()->createPublic($this->publicData());

        $this->assertSame(0, $this->inserted[0]['is_requester_verified']);
    }

    public function testCreatePublicIsUnverifiedWhenHrHasNothingToCompare(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(['name' => 'Sari']);

        $this->service()->createPublic($this->publicData());

        $this->assertSame(0, $this->inserted[0]['is_requester_verified']);
    }

    // --- Lampiran saat tiket dibuat ---------------------------------------

    public function testCreateValidatesFilesBeforeInsertAndStoresThemAfterwards(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('find')->willReturn($this->actorUser());
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->attachmentService->expects($this->once())->method('validateAll')->with([$file])->willReturn([$file]);
        $this->attachmentService->expects($this->once())->method('store')->with('t1', null, $file)->willReturn(['id' => 'a1', 'storage_key' => 'k']);

        $this->service()->create($this->data(), $this->actor(), [$file]);

        $this->assertCount(1, $this->inserted);
    }

    public function testCreateRejectsInvalidFilesBeforeAnythingIsSaved(): void
    {
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('find')->willReturn($this->actorUser());
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->attachmentService->method('validateAll')->willThrowException(new BusinessException('terlalu besar'));

        try {
            $this->service()->create($this->data(), $this->actor(), [$this->createMock(UploadedFile::class)]);
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException) {
            $this->assertSame([], $this->inserted);
        }
    }

    public function testCreatePublicAcceptsFiles(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $this->categories->method('find')->willReturn($this->category());
        $this->hr->method('getHelpdeskUserData')->willReturn(null);
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->attachmentService->method('validateAll')->willReturn([$file]);
        $this->attachmentService->expects($this->once())->method('store')->with('t1', null, $file)->willReturn(['id' => 'a1', 'storage_key' => 'k']);

        $this->service()->createPublic($this->publicData(), [$file]);
    }

    public function testStoredFilesAreDiscardedWhenALaterFileFails(): void
    {
        $first  = $this->createMock(UploadedFile::class);
        $second = $this->createMock(UploadedFile::class);
        $this->categories->method('find')->willReturn($this->category());
        $this->users->method('find')->willReturn($this->actorUser());
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->attachmentService->method('validateAll')->willReturn([$first, $second]);
        $this->attachmentService->method('store')->willReturnOnConsecutiveCalls(
            ['id' => 'a1', 'storage_key' => 'k1'],
            $this->throwException(new BusinessException('gagal')),
        );
        $this->attachmentService->expects($this->once())->method('discard')->with([['id' => 'a1', 'storage_key' => 'k1']]);
        $this->expectException(BusinessException::class);

        $this->service()->create($this->data(), $this->actor(), [$first, $second]);
    }

    // --- Status dan PIC ----------------------------------------------------

    public function testChangeStatusRejectsIllegalTransition(): void
    {
        $this->tickets->expects($this->never())->method('update');
        $this->expectException(BusinessException::class);

        $this->service()->changeStatus('t1', TicketStatus::Resolved, 'u1');
    }

    public function testChangeStatusUpdatesAndRecordsActivity(): void
    {
        $this->tickets->expects($this->once())->method('update')->with('t1', ['status' => 'IN_PROGRESS'])->willReturn(true);
        $this->activities->expects($this->once())->method('record')->with('t1', 'u1', 'status_changed', 'OPEN', 'IN_PROGRESS');

        $this->service()->changeStatus('t1', TicketStatus::InProgress, 'u1');
    }

    public function testAssignRejectsUserThatIsNotACandidate(): void
    {
        $this->users->method('withPermission')->willReturn([['id' => 'u9', 'name' => 'X', 'employee_no' => '9']]);
        $this->tickets->expects($this->never())->method('update');
        $this->expectException(BusinessException::class);

        $this->service()->assign('t1', 'assignee', 'u5', 'u1');
    }

    public function testAssignSetsPicAndRecordsActivity(): void
    {
        $this->users->method('withPermission')->willReturn([['id' => 'u9', 'name' => 'Siti', 'employee_no' => '9']]);
        $this->tickets->expects($this->once())->method('update')->with('t1', ['resolver_id' => 'u9'])->willReturn(true);
        $this->activities->expects($this->once())->method('record')->with('t1', 'u1', 'resolver_changed', null, 'Siti');

        $this->service()->assign('t1', 'resolver', 'u9', 'u1');
    }

    public function testAssignNullClearsPic(): void
    {
        $this->tickets->expects($this->once())->method('update')->with('t1', ['assignee_id' => null])->willReturn(true);

        $this->service()->assign('t1', 'assignee', null, 'u1');
    }

    // --- Daftar ------------------------------------------------------------

    public function testPaginateVisibleUsesEffectiveCategoriesAndViewAllFlag(): void
    {
        $user = new User(['id' => 'u1']);
        $this->users->method('find')->with('u1')->willReturn($user);
        $this->access->method('effectiveCategoryIds')->with($user)->willReturn(['c1', 'c2']);
        $this->tickets->expects($this->once())->method('visibleTo')->with($this->actor(), ['c1', 'c2'], true)->willReturnSelf();
        $this->tickets->expects($this->never())->method('onlyRequestedBy');
        $this->tickets->method('paginateList')->willReturn([]);

        $result = $this->service()->paginateVisible($this->actor(), true);

        $this->assertSame([], $result['tickets']);
    }

    public function testPaginateVisibleMineRestrictsToRequesterOrFiler(): void
    {
        $this->users->method('find')->willReturn(new User(['id' => 'u1']));
        $this->access->method('effectiveCategoryIds')->willReturn([]);
        $this->tickets->method('visibleTo')->willReturnSelf();
        $this->tickets->expects($this->once())->method('onlyRequestedBy')->with($this->actor())->willReturnSelf();
        $this->tickets->method('paginateList')->willReturn([]);

        $this->service()->paginateVisible($this->actor(), false, TicketService::SCOPE_MINE);
    }

    public function testPaginateVisibleAssignedRestrictsToPic(): void
    {
        $this->users->method('find')->willReturn(new User(['id' => 'u1']));
        $this->access->method('effectiveCategoryIds')->willReturn([]);
        $this->tickets->method('visibleTo')->willReturnSelf();
        $this->tickets->expects($this->once())->method('onlyAssignedTo')->with('u1')->willReturnSelf();
        $this->tickets->method('paginateList')->willReturn([]);

        $this->service()->paginateVisible($this->actor(), false, TicketService::SCOPE_ASSIGNED);
    }
}
