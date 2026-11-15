<?php

use App\Entities\TicketStatus;
use App\Exceptions\BusinessException;
use App\Models\TicketCommentModel;
use App\Services\AttachmentService;
use App\Services\TicketCommentService;
use App\Services\TicketService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TicketCommentServiceTest extends CIUnitTestCase
{
    private const TICKET_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';

    private TicketService $tickets;
    private TicketCommentModel $comments;
    private AttachmentService $attachments;

    /** @var list<array> */
    private array $inserted = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tickets     = $this->createMock(TicketService::class);
        $this->comments    = $this->createMock(TicketCommentModel::class);
        $this->attachments = $this->createMock(AttachmentService::class);

        $this->comments->method('insert')->willReturnCallback(function (array $row) {
            $this->inserted[] = $row;

            return 'c1';
        });
        $this->comments->method('find')->willReturn(['id' => 'c1']);
        $this->attachments->method('validateAll')->willReturnArgument(0);
    }

    private function service(): TicketCommentService
    {
        return new TicketCommentService($this->tickets, $this->comments, $this->attachments, $this->createMock(BaseConnection::class));
    }

    private function viewer(): array
    {
        return ['id' => 'u1', 'employee_no' => '00001'];
    }

    /**
     * Tiket yang terlihat oleh viewer (u1). Secara bawaan u1 adalah pemohonnya.
     */
    private function ticketIs(string $status, array $overrides = []): void
    {
        $this->tickets->method('find')->willReturn($overrides + [
            'id' => self::TICKET_ID, 'status' => $status, 'requester_id' => 'u1', 'requester_nip' => '00001',
            'is_requester_verified' => 1, 'created_by' => '00001', 'assignee_id' => null, 'resolver_id' => null,
        ]);
    }

    public function testAssignedPicReplyIsLabeledAgentAndNeverReopensTheTicket(): void
    {
        $this->ticketIs('RESOLVED', ['requester_id' => 'u9', 'requester_nip' => '00009', 'created_by' => '00009', 'assignee_id' => 'u1']);
        $this->tickets->expects($this->never())->method('changeStatus');

        $this->add(['body' => 'Mohon konfirmasi'], isAgent: false);

        $this->assertSame('AGENT', $this->inserted[0]['author_type']);
        $this->assertSame('PUBLIC', $this->inserted[0]['visibility']);
    }

    public function testViewerWhoIsNeitherRequesterNorPicCannotComment(): void
    {
        $this->ticketIs('OPEN', ['requester_id' => 'u9', 'requester_nip' => '00009', 'created_by' => '00009']);
        $this->comments->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->add(['body' => 'Saya cuma punya akses kategori']);
    }

    public function testUnverifiedNipMatchDoesNotMakeTheViewerTheRequester(): void
    {
        $this->ticketIs('RESOLVED', ['requester_id' => null, 'requester_nip' => '00001', 'is_requester_verified' => 0, 'created_by' => null]);
        $this->tickets->expects($this->never())->method('changeStatus');
        $this->expectException(BusinessException::class);

        $this->add(['body' => 'Saya mengaku pemilik NIK ini']);
    }

    public function testTheFilerOfAnOnBehalfTicketCountsAsRequesterSide(): void
    {
        $this->ticketIs('RESOLVED', ['requester_id' => 'u9', 'requester_nip' => '00009', 'created_by' => '00001']);
        $this->tickets->expects($this->once())->method('changeStatus')->with(self::TICKET_ID, TicketStatus::Reopened, 'u1');

        $this->add(['body' => 'Belum beres']);
    }

    private function add(array $input, bool $isAgent = false, array $files = []): array
    {
        return $this->service()->add(self::TICKET_ID, $this->viewer(), false, $isAgent, $input, $files);
    }

    public function testAddRejectsTicketThatIsNotVisible(): void
    {
        $this->tickets->method('find')->willReturn(null);
        $this->expectException(BusinessException::class);

        $this->add(['body' => 'Halo']);
    }

    public function testAddRejectsFinalTicket(): void
    {
        $this->ticketIs('CLOSED');
        $this->comments->expects($this->never())->method('insert');
        $this->expectException(BusinessException::class);

        $this->add(['body' => 'Halo']);
    }

    public function testAddRejectsEmptyBody(): void
    {
        $this->ticketIs('OPEN');
        $this->expectException(BusinessException::class);

        $this->add(['body' => "   \n "]);
    }

    public function testAddRejectsBodyLongerThanTheLimit(): void
    {
        $this->ticketIs('OPEN');
        $this->expectException(BusinessException::class);

        $this->add(['body' => str_repeat('a', 5001)]);
    }

    public function testRequesterCannotPostInternalNote(): void
    {
        $this->ticketIs('OPEN');

        $this->add(['body' => 'Halo', 'visibility' => 'INTERNAL'], isAgent: false);

        $this->assertSame('PUBLIC', $this->inserted[0]['visibility']);
        $this->assertSame('REQUESTER', $this->inserted[0]['author_type']);
    }

    public function testAgentCanPostInternalNote(): void
    {
        $this->ticketIs('OPEN');

        $this->add(['body' => 'Catatan', 'visibility' => 'INTERNAL'], isAgent: true);

        $this->assertSame('INTERNAL', $this->inserted[0]['visibility']);
        $this->assertSame('AGENT', $this->inserted[0]['author_type']);
        $this->assertSame('u1', $this->inserted[0]['author_id']);
    }

    public function testAgentDefaultsToPublicReply(): void
    {
        $this->ticketIs('OPEN');

        $this->add(['body' => 'Balasan'], isAgent: true);

        $this->assertSame('PUBLIC', $this->inserted[0]['visibility']);
    }

    public function testRequesterReplyOnResolvedTicketReopensIt(): void
    {
        $this->ticketIs('RESOLVED');
        $this->tickets->expects($this->once())->method('changeStatus')->with(self::TICKET_ID, TicketStatus::Reopened, 'u1');

        $this->add(['body' => 'Masih belum bisa']);
    }

    public function testAgentReplyOnResolvedTicketDoesNotReopenIt(): void
    {
        $this->ticketIs('RESOLVED');
        $this->tickets->expects($this->never())->method('changeStatus');

        $this->add(['body' => 'Mohon konfirmasi'], isAgent: true);
    }

    public function testRequesterReplyOnOpenTicketDoesNotChangeStatus(): void
    {
        $this->ticketIs('OPEN');
        $this->tickets->expects($this->never())->method('changeStatus');

        $this->add(['body' => 'Tambahan info']);
    }

    public function testFilesAreValidatedBeforeAndStoredWithTheCommentId(): void
    {
        $this->ticketIs('OPEN');
        $file = $this->createMock(UploadedFile::class);
        $this->attachments->expects($this->once())->method('validateAll')->with([$file])->willReturn([$file]);
        $this->attachments->expects($this->once())->method('store')->with(self::TICKET_ID, 'c1', $file)->willReturn(['id' => 'a1', 'storage_key' => 'k']);

        $this->add(['body' => 'Lihat lampiran'], files: [$file]);
    }

    public function testStoredFilesAreDiscardedWhenALaterFileFails(): void
    {
        $this->ticketIs('OPEN');
        $first  = $this->createMock(UploadedFile::class);
        $second = $this->createMock(UploadedFile::class);
        $this->attachments->method('validateAll')->willReturn([$first, $second]);
        $this->attachments->method('store')->willReturnOnConsecutiveCalls(
            ['id' => 'a1', 'storage_key' => 'k1'],
            $this->throwException(new BusinessException('gagal')),
        );
        $this->attachments->expects($this->once())->method('discard')->with([['id' => 'a1', 'storage_key' => 'k1']]);
        $this->expectException(BusinessException::class);

        $this->add(['body' => 'Dua file'], files: [$first, $second]);
    }

    public function testThreadGroupsAttachmentsUnderTheirComment(): void
    {
        $this->comments->method('forTicket')->with(self::TICKET_ID, true)->willReturn([['id' => 'c1', 'body' => 'x'], ['id' => 'c2', 'body' => 'y']]);
        $this->attachments->method('forTicket')->with(self::TICKET_ID, true)->willReturn([
            ['id' => 'a0', 'comment_id' => null],
            ['id' => 'a1', 'comment_id' => 'c2'],
        ]);

        $thread = $this->service()->thread(self::TICKET_ID, true);

        $this->assertSame([], $thread['comments'][0]['attachments']);
        $this->assertSame('a1', $thread['comments'][1]['attachments'][0]['id']);
        $this->assertSame('a0', $thread['ticketAttachments'][0]['id']);
    }

    public function testDownloadHidesInternalAttachmentFromNonAgents(): void
    {
        $this->attachments->method('findWithVisibility')->willReturn([
            'id' => 'a1', 'ticket_id' => self::TICKET_ID, 'comment_visibility' => 'INTERNAL',
        ]);
        $this->tickets->method('find')->willReturn(['id' => self::TICKET_ID, 'status' => 'OPEN']);
        $this->expectException(BusinessException::class);

        $this->service()->attachmentForDownload('a1', $this->viewer(), false, false);
    }

    public function testDownloadRejectsAttachmentOfInvisibleTicket(): void
    {
        $this->attachments->method('findWithVisibility')->willReturn(['id' => 'a1', 'ticket_id' => self::TICKET_ID, 'comment_visibility' => null]);
        $this->tickets->method('find')->willReturn(null);
        $this->expectException(BusinessException::class);

        $this->service()->attachmentForDownload('a1', $this->viewer(), false, false);
    }

    public function testDownloadReturnsPathNameAndTypeForAnAllowedAttachment(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($tmp, 'x');

        $attachment = ['id' => 'a1', 'ticket_id' => self::TICKET_ID, 'comment_visibility' => 'INTERNAL', 'original_name' => 'log.txt', 'content_type' => 'text/plain'];
        $this->attachments->method('findWithVisibility')->willReturn($attachment);
        $this->attachments->method('absolutePath')->willReturn($tmp);
        $this->tickets->method('find')->willReturn(['id' => self::TICKET_ID, 'status' => 'OPEN']);

        $result = $this->service()->attachmentForDownload('a1', $this->viewer(), false, true);
        @unlink($tmp);

        $this->assertSame(['path' => $tmp, 'name' => 'log.txt', 'type' => 'text/plain'], $result);
    }

    public function testDownloadRejectsWhenTheFileIsMissingOnDisk(): void
    {
        $this->attachments->method('findWithVisibility')->willReturn(['id' => 'a1', 'ticket_id' => self::TICKET_ID, 'comment_visibility' => null]);
        $this->attachments->method('absolutePath')->willReturn('/tidak/ada/file.txt');
        $this->tickets->method('find')->willReturn(['id' => self::TICKET_ID, 'status' => 'OPEN']);
        $this->expectException(BusinessException::class);

        $this->service()->attachmentForDownload('a1', $this->viewer(), false, false);
    }
}
