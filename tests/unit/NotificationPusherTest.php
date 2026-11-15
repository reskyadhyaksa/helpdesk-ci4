<?php

use App\Services\NotificationPusher;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NotificationPusherTest extends CIUnitTestCase
{
    /** @var resource|false */
    private $server;
    private int $port;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertNotFalse($this->server, "server uji gagal: {$error}");
        $this->port = (int) substr(strrchr(stream_socket_get_name($this->server, false), ':'), 1);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            fclose($this->server);
        }

        parent::tearDown();
    }

    /** @return list<array> pesan JSON yang diterima server uji */
    private function received(int $expected): array
    {
        $messages = [];

        for ($i = 0; $i < $expected; $i++) {
            $client = @stream_socket_accept($this->server, 1);
            $this->assertNotFalse($client, 'tidak ada koneksi masuk');
            $messages[] = json_decode(trim((string) fgets($client)), true);
            fclose($client);
        }

        return $messages;
    }

    public function testPushSendsOneJsonLinePerNotificationToTheInternalPort(): void
    {
        (new NotificationPusher('127.0.0.1', $this->port))->push([
            ['id' => 'n1', 'user_id' => 'u1', 'event_type' => 'status_changed', 'title' => 'T', 'body' => 'B', 'url' => '/u/tickets/x', 'created_at' => '2026-11-15 10:00:00', 'ticket_id' => 't'],
            ['id' => 'n2', 'user_id' => 'u2', 'event_type' => 'comment_added', 'title' => 'T2', 'body' => 'B2', 'url' => '/admin/tickets/x', 'created_at' => '2026-11-15 10:00:01', 'ticket_id' => 't'],
        ]);

        $messages = $this->received(2);

        $this->assertSame('u1', $messages[0]['user_id']);
        $this->assertSame('n1', $messages[0]['notification']['id']);
        $this->assertSame('/u/tickets/x', $messages[0]['notification']['url']);
        $this->assertSame('u2', $messages[1]['user_id']);
    }

    public function testPushDoesNotLeakInternalFieldsToTheBrowserPayload(): void
    {
        (new NotificationPusher('127.0.0.1', $this->port))->push([
            ['id' => 'n1', 'user_id' => 'u1', 'ticket_id' => 't', 'event_type' => 'x', 'title' => 'T', 'body' => 'B', 'url' => '/x', 'created_at' => 'd', 'secret' => 'jangan'],
        ]);

        $notification = $this->received(1)[0]['notification'];

        $this->assertArrayNotHasKey('secret', $notification);
        $this->assertArrayNotHasKey('user_id', $notification);
    }

    public function testPushToAnUnreachableServerNeverThrows(): void
    {
        fclose($this->server);

        (new NotificationPusher('127.0.0.1', $this->port))->push([
            ['id' => 'n1', 'user_id' => 'u1', 'event_type' => 'x', 'title' => 'T', 'body' => 'B', 'url' => '/x', 'created_at' => 'd'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testPushWithNothingToSendDoesNotConnect(): void
    {
        fclose($this->server);

        (new NotificationPusher('127.0.0.1', $this->port))->push([]);

        $this->addToAssertionCount(1);
    }
}
