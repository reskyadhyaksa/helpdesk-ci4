<?php

use App\Exceptions\BusinessException;
use App\Models\SystemParameterModel;
use App\Models\TicketAttachmentModel;
use App\Services\AttachmentService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AttachmentService dengan penyimpanan fisik diganti pencatat, supaya teruji tanpa upload HTTP sungguhan.
 */
final class RecordingAttachmentService extends AttachmentService
{
    /** @var list<string> */
    public array $moved = [];

    /** @var list<string> */
    public array $deleted = [];

    protected function moveFile(UploadedFile $file, string $directory, string $name): void
    {
        $this->moved[] = $directory . '/' . $name;
    }

    protected function deleteFile(string $path): void
    {
        $this->deleted[] = $path;
    }
}

/**
 * @internal
 */
final class AttachmentServiceTest extends CIUnitTestCase
{
    private const TICKET_ID = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';

    private TicketAttachmentModel $attachments;
    private SystemParameterModel $parameters;
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attachments = $this->createMock(TicketAttachmentModel::class);
        $this->parameters  = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('value')->willReturnMap([
            [SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS, 'jpg,png,pdf'],
        ]);
        $this->parameters->method('int')->willReturnMap([
            [SystemParameterModel::ATTACHMENT_MAX_SIZE_MB, 2],
            [SystemParameterModel::ATTACHMENT_MAX_FILES, 2],
        ]);

        $this->tmp = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($this->tmp, 'isi file');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        parent::tearDown();
    }

    private function service(): RecordingAttachmentService
    {
        return new RecordingAttachmentService($this->attachments, $this->parameters);
    }

    private function upload(string $name = 'bukti.png', int $size = 1000, bool $valid = true, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn($valid);
        $file->method('getError')->willReturn($error);
        $file->method('getSize')->willReturn($size);
        $file->method('getClientName')->willReturn($name);
        $file->method('getClientExtension')->willReturn(pathinfo($name, PATHINFO_EXTENSION));
        $file->method('getMimeType')->willReturn('image/png');
        $file->method('getTempName')->willReturn($this->tmp);

        return $file;
    }

    public function testRejectsDisallowedExtension(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validate($this->upload('skrip.php'));
    }

    public function testRejectsFileLargerThanTheParameterLimit(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validate($this->upload('besar.png', 2 * 1024 * 1024 + 1));
    }

    public function testAcceptsFileExactlyAtTheLimit(): void
    {
        $this->service()->validate($this->upload('pas.png', 2 * 1024 * 1024));

        $this->addToAssertionCount(1);
    }

    public function testRejectsEmptyFile(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validate($this->upload('kosong.png', 0));
    }

    public function testUploadExceedingThePhpLimitReportsTheSizeLimitNotAGenericFailure(): void
    {
        try {
            $this->service()->validate($this->upload('besar.pdf', 0, valid: false, error: UPLOAD_ERR_INI_SIZE));
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('melebihi', $e->getMessage());
        }
    }

    public function testDefaultLimitsFitInsidePhpsDefaultPostMaxSize(): void
    {
        $defaults = SystemParameterModel::DEFAULTS;
        $total    = (int) $defaults[SystemParameterModel::ATTACHMENT_MAX_SIZE_MB]['value']
            * (int) $defaults[SystemParameterModel::ATTACHMENT_MAX_FILES]['value'];

        // post_max_size bawaan PHP = 8M; bila terlampaui seluruh isian form (termasuk token CSRF) hilang
        $this->assertLessThanOrEqual(8, $total);
    }

    public function testRemoveDeletesTheStoredFileAndReportsSuccess(): void
    {
        $service = $this->service();

        $ok = $service->remove(['storage_key' => self::TICKET_ID . '/01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0c.png']);

        $this->assertTrue($ok);
        $this->assertCount(1, $service->deleted);
        $this->assertStringEndsWith(self::TICKET_ID . '/01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0c.png', $service->deleted[0]);
    }

    public function testRemoveRefusesAStorageKeyThatCouldEscapeTheUploadFolder(): void
    {
        $service = $this->service();

        foreach (['../../.env', self::TICKET_ID . '/../../x.png', '/etc/passwd', 'a/b', '', self::TICKET_ID . '/x.png.php'] as $key) {
            $this->assertFalse($service->remove(['storage_key' => $key]), "kunci '{$key}' harus ditolak");
        }

        $this->assertSame([], $service->deleted);
    }

    public function testRejectsFailedUpload(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validate($this->upload('rusak.png', 1000, valid: false, error: UPLOAD_ERR_PARTIAL));
    }

    public function testDoubleExtensionIsJudgedByTheLastExtension(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validate($this->upload('foto.png.php'));
    }

    public function testValidateAllIgnoresEmptyFileInputs(): void
    {
        $result = $this->service()->validateAll([
            $this->upload('a.png'),
            $this->upload('', 0, valid: false, error: UPLOAD_ERR_NO_FILE),
        ]);

        $this->assertCount(1, $result);
    }

    public function testValidateAllRejectsMoreFilesThanTheLimit(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->validateAll([$this->upload('a.png'), $this->upload('b.png'), $this->upload('c.png')]);
    }

    public function testStoreSavesMetadataAndUsesAGeneratedNameWithTheValidatedExtension(): void
    {
        $row = null;
        $this->attachments->method('insert')->willReturnCallback(static function (array $data) use (&$row) {
            $row = $data;

            return 'a1';
        });

        $service = $this->service();
        $service->store(self::TICKET_ID, 'c1', $this->upload('Bukti Error.PNG', 1234));

        $this->assertSame(self::TICKET_ID, $row['ticket_id']);
        $this->assertSame('c1', $row['comment_id']);
        $this->assertSame('Bukti Error.PNG', $row['original_name']);
        $this->assertSame(1234, $row['size_bytes']);
        $this->assertSame(hash('sha256', 'isi file'), $row['checksum_sha256']);
        $this->assertMatchesRegularExpression('#^' . self::TICKET_ID . '/[0-9a-f-]{36}\.png$#', $row['storage_key']);
        $this->assertCount(1, $service->moved);
        $this->assertStringNotContainsString('Bukti', $service->moved[0]);
    }

    public function testStoreRemovesTheMovedFileWhenTheDatabaseInsertFails(): void
    {
        $this->attachments->method('insert')->willReturn(false);
        $this->attachments->method('errors')->willReturn(['x' => 'gagal']);

        $service = $this->service();

        try {
            $service->store(self::TICKET_ID, null, $this->upload());
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException) {
            $this->assertCount(1, $service->deleted);
            $this->assertSame($service->moved[0], $service->deleted[0]);
        }
    }
}
