<?php

use App\Services\MessageQueue;
use CodeIgniter\Test\CIUnitTestCase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * MessageQueue dengan channel RabbitMQ diganti palsu, supaya teruji tanpa server.
 */
final class FakeChannelMessageQueue extends MessageQueue
{
    public function __construct(private readonly AMQPChannel $fake, ?string $queueName = 'test.queue')
    {
        parent::__construct($queueName, 'localhost', 5672, 'guest', 'guest');
    }

    protected function channel(): AMQPChannel
    {
        return $this->fake;
    }
}

/**
 * @internal
 */
final class MessageQueueTest extends CIUnitTestCase
{
    public function testPublishDeclaresADurableQueueAndSendsAPersistentJsonMessage(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('queue_declare')->with('test.queue', false, true, false, false);
        $channel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(static fn (AMQPMessage $m) => $m->getBody() === '{"outbox_id":"abc"}'
                    && $m->get('delivery_mode') === AMQPMessage::DELIVERY_MODE_PERSISTENT),
                '',
                'test.queue',
            );

        (new FakeChannelMessageQueue($channel))->publish(['outbox_id' => 'abc']);
    }

    public function testQueueNameComesFromTheEnvWhenNoneIsGiven(): void
    {
        $_ENV['RABBITMQ_QUEUE'] = 'custom.queue';

        try {
            $channel = $this->createMock(AMQPChannel::class);
            $channel->expects($this->once())->method('queue_declare')->with('custom.queue', false, true, false, false);

            (new FakeChannelMessageQueue($channel, null))->publish(['outbox_id' => 'abc']);
        } finally {
            unset($_ENV['RABBITMQ_QUEUE']);
        }
    }

    public function testQueueNameDefaultsToHelpdeskNotificationsWithoutEnv(): void
    {
        unset($_ENV['RABBITMQ_QUEUE'], $_SERVER['RABBITMQ_QUEUE']);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('queue_declare')->with('helpdesk.notifications', false, true, false, false);

        (new FakeChannelMessageQueue($channel, null))->publish(['outbox_id' => 'abc']);
    }

    public function testConsumeHandsTheDecodedPayloadToTheHandlerAndAcks(): void
    {
        $callback = null;
        $channel  = $this->createMock(AMQPChannel::class);
        $channel->method('basic_consume')->willReturnCallback(static function (...$args) use (&$callback) {
            $callback = $args[6];

            return 'tag';
        });
        $channel->method('is_consuming')->willReturn(false);

        (new FakeChannelMessageQueue($channel))->consume(static function (array $payload) use (&$received): void {
            $received = $payload;
        });

        $message = $this->createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn('{"outbox_id":"abc"}');
        $message->expects($this->once())->method('ack');
        $message->expects($this->never())->method('nack');

        $callback($message);

        $this->assertSame(['outbox_id' => 'abc'], $received);
    }

    public function testConsumeRejectsWithoutRequeueWhenTheHandlerThrows(): void
    {
        $callback = null;
        $channel  = $this->createMock(AMQPChannel::class);
        $channel->method('basic_consume')->willReturnCallback(static function (...$args) use (&$callback) {
            $callback = $args[6];

            return 'tag';
        });
        $channel->method('is_consuming')->willReturn(false);

        (new FakeChannelMessageQueue($channel))->consume(static function (): void {
            throw new RuntimeException('boom');
        });

        $message = $this->createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn('{"outbox_id":"abc"}');
        $message->expects($this->once())->method('nack')->with(false);
        $message->expects($this->never())->method('ack');

        $callback($message);
    }

    public function testConsumeDropsMessagesThatAreNotJsonObjects(): void
    {
        $callback = null;
        $channel  = $this->createMock(AMQPChannel::class);
        $channel->method('basic_consume')->willReturnCallback(static function (...$args) use (&$callback) {
            $callback = $args[6];

            return 'tag';
        });
        $channel->method('is_consuming')->willReturn(false);

        $called = false;
        (new FakeChannelMessageQueue($channel))->consume(static function () use (&$called): void {
            $called = true;
        });

        $message = $this->createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn('bukan json');
        $message->expects($this->once())->method('nack')->with(false);

        $callback($message);

        $this->assertFalse($called);
    }
}
