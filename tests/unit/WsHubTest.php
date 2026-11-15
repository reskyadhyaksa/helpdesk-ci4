<?php

use App\Services\WsHub;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WsHubTest extends CIUnitTestCase
{
    /** @var array<string, list<string>> pesan yang diterima tiap koneksi */
    private array $inbox = [];

    private function connect(WsHub $hub, string $connectionId, string $userId): array
    {
        $this->inbox[$connectionId] = [];

        return $hub->register($connectionId, $userId, function (string $data) use ($connectionId): void {
            $this->inbox[$connectionId][] = $data;
        });
    }

    public function testPushReachesOnlyTheConnectionsOfThatUser(): void
    {
        $hub = new WsHub();
        $this->connect($hub, 'c1', 'alice');
        $this->connect($hub, 'c2', 'alice');
        $this->connect($hub, 'c3', 'bob');

        $sent = $hub->push('alice', ['id' => 'n1', 'title' => 'Halo']);

        $this->assertSame(2, $sent);
        $this->assertCount(1, $this->inbox['c1']);
        $this->assertCount(1, $this->inbox['c2']);
        $this->assertSame([], $this->inbox['c3']);
        $this->assertSame(['notification' => ['id' => 'n1', 'title' => 'Halo']], json_decode($this->inbox['c1'][0], true));
    }

    public function testAnUnregisteredConnectionStopsReceiving(): void
    {
        $hub = new WsHub();
        $this->connect($hub, 'c1', 'alice');
        $hub->unregister('c1');

        $this->assertSame(0, $hub->push('alice', ['id' => 'n1']));
        $this->assertSame([], $this->inbox['c1']);
    }

    public function testPushToAUserWithoutConnectionsSendsNothing(): void
    {
        $this->assertSame(0, (new WsHub())->push('ghost', ['id' => 'n1']));
    }

    public function testTheOldestConnectionIsEvictedWhenTheLimitIsExceeded(): void
    {
        $hub = new WsHub(maxConnectionsPerUser: 2);
        $this->connect($hub, 'c1', 'alice');
        $this->connect($hub, 'c2', 'alice');

        $evicted = $this->connect($hub, 'c3', 'alice');

        $this->assertSame(['c1'], $evicted);
        $hub->push('alice', ['id' => 'n1']);
        $this->assertSame([], $this->inbox['c1']);
        $this->assertCount(1, $this->inbox['c3']);
    }

    public function testAFailingConnectionDoesNotStopDeliveryToTheOthers(): void
    {
        $hub = new WsHub();
        $hub->register('bad', 'alice', static function (): void {
            throw new RuntimeException('putus');
        });
        $this->connect($hub, 'good', 'alice');

        $sent = $hub->push('alice', ['id' => 'n1']);

        $this->assertSame(1, $sent);
        $this->assertCount(1, $this->inbox['good']);
    }

    public function testPingGoesToEveryConnection(): void
    {
        $hub = new WsHub();
        $this->connect($hub, 'c1', 'alice');
        $this->connect($hub, 'c2', 'bob');

        $hub->ping();

        $this->assertSame('{"type":"ping"}', $this->inbox['c1'][0]);
        $this->assertSame('{"type":"ping"}', $this->inbox['c2'][0]);
    }

    public function testParseInternalLineAcceptsOnlyWellFormedPushCommands(): void
    {
        $this->assertSame(
            ['user_id' => 'u1', 'notification' => ['id' => 'n1']],
            WsHub::parseInternalLine('{"user_id":"u1","notification":{"id":"n1"}}'),
        );

        foreach (['', 'bukan json', '[]', '{"user_id":"","notification":{}}', '{"user_id":"u1"}', '{"user_id":1,"notification":{}}'] as $bad) {
            $this->assertNull(WsHub::parseInternalLine($bad), "baris '{$bad}' harus ditolak");
        }
    }
}
