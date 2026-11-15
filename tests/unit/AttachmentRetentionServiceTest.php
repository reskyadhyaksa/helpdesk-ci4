<?php

use App\Models\SystemParameterModel;
use App\Models\TicketAttachmentModel;
use App\Services\AttachmentRetentionService;
use App\Services\AttachmentService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AttachmentRetentionServiceTest extends CIUnitTestCase
{
    private TicketAttachmentModel $attachments;
    private AttachmentService $files;
    private SystemParameterModel $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attachments = $this->createMock(TicketAttachmentModel::class);
        $this->files       = $this->createMock(AttachmentService::class);
        $this->parameters  = $this->createMock(SystemParameterModel::class);
    }

    private function service(int $days = 14): AttachmentRetentionService
    {
        $this->parameters->method('int')->with(SystemParameterModel::ATTACHMENT_RETENTION_DAYS)->willReturn($days);

        return new AttachmentRetentionService($this->attachments, $this->files, $this->parameters);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-30 12:00:00');
    }

    public function testOnlyTicketsClosedBeforeTheRetentionCutoffAreQueried(): void
    {
        $this->attachments->expects($this->once())
            ->method('dueForPurge')
            ->with('2026-11-16 12:00:00', 500)
            ->willReturn([]);

        $this->assertSame(0, $this->service(14)->purge($this->now()));
    }

    public function testEachDueAttachmentIsDeletedFromDiskAndMarkedPurged(): void
    {
        $rows = [['id' => 'a1', 'storage_key' => 'k1'], ['id' => 'a2', 'storage_key' => 'k2']];
        $this->attachments->method('dueForPurge')->willReturn($rows);
        $this->files->expects($this->exactly(2))->method('remove')->willReturn(true);
        $this->attachments->expects($this->exactly(2))->method('markPurged')->with($this->logicalOr('a1', 'a2'), '2026-11-30 12:00:00');

        $this->assertSame(2, $this->service()->purge($this->now()));
    }

    public function testRetentionOfZeroDaysDisablesThePurgeAndNeverQueries(): void
    {
        $this->attachments->expects($this->never())->method('dueForPurge');
        $this->files->expects($this->never())->method('remove');

        $this->assertSame(0, $this->service(0)->purge($this->now()));
    }

    public function testANegativeRetentionAlsoDisablesThePurge(): void
    {
        $this->attachments->expects($this->never())->method('dueForPurge');

        $this->assertSame(0, $this->service(-5)->purge($this->now()));
    }

    public function testAFileThatCannotBeRemovedIsLeftUnmarkedAndTheRestContinue(): void
    {
        $this->attachments->method('dueForPurge')->willReturn([['id' => 'bad', 'storage_key' => 'k1'], ['id' => 'good', 'storage_key' => 'k2']]);
        $this->files->method('remove')->willReturnCallback(static fn (array $a): bool => $a['id'] === 'good');
        $this->attachments->expects($this->once())->method('markPurged')->with('good', $this->anything());

        $this->assertSame(1, $this->service()->purge($this->now()));
    }

    public function testAFileThatThrowsWhenRemovedDoesNotAbortTheRun(): void
    {
        // Mis. file terkunci di Windows: unlink() menjadi ErrorException dan tidak boleh menghentikan sisa lampiran
        $this->attachments->method('dueForPurge')->willReturn([
            ['id' => 'locked', 'storage_key' => 'k1'],
            ['id' => 'good1', 'storage_key' => 'k2'],
            ['id' => 'good2', 'storage_key' => 'k3'],
        ]);
        $this->files->method('remove')->willReturnCallback(static function (array $a): bool {
            if ($a['id'] === 'locked') {
                throw new ErrorException('unlink(): Permission denied');
            }

            return true;
        });
        $marked = [];
        $this->attachments->method('markPurged')->willReturnCallback(static function (string $id) use (&$marked): void {
            $marked[] = $id;
        });

        $purged = $this->service()->purge($this->now());

        $this->assertSame(2, $purged);
        $this->assertSame(['good1', 'good2'], $marked);
    }

    public function testAFailureWhileMarkingOneRowDoesNotAbortTheRunEither(): void
    {
        $this->attachments->method('dueForPurge')->willReturn([['id' => 'a', 'storage_key' => 'k1'], ['id' => 'b', 'storage_key' => 'k2']]);
        $this->files->method('remove')->willReturn(true);
        $this->attachments->method('markPurged')->willReturnCallback(static function (string $id): void {
            if ($id === 'a') {
                throw new RuntimeException('db');
            }
        });

        $this->assertSame(1, $this->service()->purge($this->now()));
    }

    public function testASingleRunIsBoundedByTheBatchLimit(): void
    {
        $this->attachments->expects($this->once())->method('dueForPurge')->with($this->anything(), 50)->willReturn([]);

        $this->service()->purge($this->now(), 50);
    }
}
