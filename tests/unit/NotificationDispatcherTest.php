<?php

use App\Models\NotificationOutboxModel;
use App\Services\MessageQueue;
use App\Services\NotificationDispatcher;
use App\Services\WhatsAppService;
use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationDispatcherTest extends CIUnitTestCase
{
    private NotificationOutboxModel $outbox;
    private MessageQueue $queue;
    private Email $email;
    private WhatsAppService $whatsapp;

    /** @var list<array> perubahan yang ditulis ke outbox */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outbox   = $this->createMock(NotificationOutboxModel::class);
        $this->queue    = $this->createMock(MessageQueue::class);
        $this->email    = $this->createMock(Email::class);
        $this->whatsapp = $this->createMock(WhatsAppService::class);

        foreach (['clear', 'setTo', 'setSubject', 'setMessage'] as $fluent) {
            $this->email->method($fluent)->willReturnSelf();
        }

        $this->outbox->method('claim')->willReturn(true);
        $this->outbox->method('update')->willReturnCallback(function ($id, array $data): bool {
            $this->updates[] = $data;

            return true;
        });
    }

    private function dispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher($this->outbox, $this->queue, $this->email, $this->whatsapp);
    }

    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'o1', 'channel' => 'EMAIL', 'recipient' => 'sari@x.id', 'status' => 'PENDING',
            'attempt_count' => 0, 'next_attempt_at' => null,
            'payload' => json_encode(['subject' => 'Judul', 'body' => 'Isi pesan']),
        ];
    }

    public function testDeliversAnEmailAndMarksItSent(): void
    {
        $this->outbox->method('find')->willReturn($this->row());
        $this->email->expects($this->once())->method('setTo')->with('sari@x.id')->willReturnSelf();
        $this->email->expects($this->once())->method('setSubject')->with('Judul')->willReturnSelf();
        $this->email->expects($this->once())->method('setMessage')->with('Isi pesan')->willReturnSelf();
        $this->email->method('send')->willReturn(true);

        $this->dispatcher()->deliver('o1');

        $this->assertSame('SENT', $this->updates[0]['status']);
        $this->assertSame(1, $this->updates[0]['attempt_count']);
        $this->assertNotNull($this->updates[0]['sent_at']);
    }

    public function testDeliversWhatsAppWithAnInternationalNumber(): void
    {
        $this->outbox->method('find')->willReturn($this->row(['channel' => 'WHATSAPP', 'recipient' => '0812-3456-7890']));
        $this->whatsapp->expects($this->once())->method('sendMessage')->with('6281234567890', 'Isi pesan')->willReturn(true);

        $this->dispatcher()->deliver('o1');

        $this->assertSame('SENT', $this->updates[0]['status']);
    }

    public function testDoesNotSendAgainWhenAlreadySentOrFailed(): void
    {
        $this->outbox->method('find')->willReturnOnConsecutiveCalls($this->row(['status' => 'SENT']), $this->row(['status' => 'FAILED']));
        $this->email->expects($this->never())->method('send');

        $this->dispatcher()->deliver('o1');
        $this->dispatcher()->deliver('o1');

        $this->assertSame([], $this->updates);
    }

    public function testIgnoresAnUnknownOutboxId(): void
    {
        $this->outbox->method('find')->willReturn(null);
        $this->email->expects($this->never())->method('send');

        $this->dispatcher()->deliver('hilang');

        $this->assertSame([], $this->updates);
    }

    public function testDoesNotSendBeforeTheScheduledRetryTime(): void
    {
        $this->outbox->method('find')->willReturn($this->row(['attempt_count' => 1, 'next_attempt_at' => date('Y-m-d H:i:s', time() + 3600)]));
        $this->email->expects($this->never())->method('send');

        $this->dispatcher()->deliver('o1');

        $this->assertSame([], $this->updates);
    }

    public function testAFailedSendIsRetriedLaterAndTheErrorIsRecorded(): void
    {
        $this->outbox->method('find')->willReturn($this->row());
        $this->email->method('send')->willReturn(false);
        $this->email->method('printDebugger')->willReturn('SMTP tidak bisa dihubungi');

        $this->dispatcher()->deliver('o1');

        $update = $this->updates[0];
        $this->assertSame('PENDING', $update['status']);
        $this->assertSame(1, $update['attempt_count']);
        $this->assertGreaterThan(time(), strtotime($update['next_attempt_at']));
        $this->assertNotSame('', (string) $update['last_error']);
    }

    public function testAnExceptionFromTheSenderCountsAsAFailure(): void
    {
        $this->outbox->method('find')->willReturn($this->row(['channel' => 'WHATSAPP', 'recipient' => '0812']));
        $this->whatsapp->method('sendMessage')->willThrowException(new RuntimeException('timeout'));

        $this->dispatcher()->deliver('o1');

        $this->assertSame('PENDING', $this->updates[0]['status']);
        $this->assertStringContainsString('timeout', $this->updates[0]['last_error']);
    }

    public function testGivesUpAfterTheMaximumNumberOfAttempts(): void
    {
        $this->outbox->method('find')->willReturn($this->row(['attempt_count' => NotificationDispatcher::MAX_ATTEMPTS - 1]));
        $this->email->method('send')->willReturn(false);

        $this->dispatcher()->deliver('o1');

        $this->assertSame('FAILED', $this->updates[0]['status']);
        $this->assertSame(NotificationDispatcher::MAX_ATTEMPTS, $this->updates[0]['attempt_count']);
        $this->assertNull($this->updates[0]['next_attempt_at']);
    }

    public function testTheLoserOfTheClaimDoesNotSendTheMessageAgain(): void
    {
        $this->outbox = $this->createMock(NotificationOutboxModel::class);
        $this->outbox->method('find')->willReturn($this->row());
        $this->outbox->expects($this->once())->method('claim')->with('o1', 0)->willReturn(false);
        $this->outbox->expects($this->never())->method('update');
        $this->email->expects($this->never())->method('send');

        $this->dispatcher()->deliver('o1');
    }

    public function testRepublishMarksTheRowsAsQueuedSoTheyAreNotRequeuedEveryMinute(): void
    {
        $this->outbox->method('findDue')->willReturn([$this->row(['id' => 'a']), $this->row(['id' => 'b'])]);
        $this->outbox->expects($this->once())->method('markQueued')->with(['a', 'b']);

        $this->dispatcher()->republishDue();
    }

    public function testRepublishDueQueuesEveryDueRow(): void
    {
        $this->outbox->method('findDue')->willReturn([$this->row(['id' => 'a']), $this->row(['id' => 'b'])]);
        $sent = [];
        $this->queue->method('publish')->willReturnCallback(static function (array $payload) use (&$sent): void {
            $sent[] = $payload['outbox_id'];
        });

        $count = $this->dispatcher()->republishDue();

        $this->assertSame(2, $count);
        $this->assertSame(['a', 'b'], $sent);
    }
}
