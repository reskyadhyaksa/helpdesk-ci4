<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Antrean RabbitMQ untuk satu nama antrean. Koneksi baru dibuat saat pertama dipakai (lazy), jadi membuat
 * objek ini tidak pernah gagal walau server mati. Konfigurasi dari .env: RABBITMQ_QUEUE/HOST/PORT/USER/PASSWORD/VHOST.
 */
class MessageQueue
{
    private readonly string $queueName;
    private readonly string $host;
    private readonly int $port;
    private readonly string $user;
    private readonly string $password;
    private readonly string $vhost;

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(
        ?string $queueName = null,
        ?string $host = null,
        ?int $port = null,
        ?string $user = null,
        ?string $password = null,
    ) {
        $this->queueName = $queueName ?? env('RABBITMQ_QUEUE', 'helpdesk.notifications');
        $this->host     = $host ?? env('RABBITMQ_HOST', 'localhost');
        $this->port     = $port ?? (int) env('RABBITMQ_PORT', 5672);
        $this->user     = $user ?? env('RABBITMQ_USER', 'guest');
        $this->password = $password ?? env('RABBITMQ_PASSWORD', 'guest');
        $this->vhost    = env('RABBITMQ_VHOST', '/');
    }

    public function __destruct()
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (Throwable) {
            // Koneksi sudah putus; tidak ada yang perlu dibersihkan
        }
    }

    /**
     * Pesan persistent ke antrean durable, jadi bertahan walau RabbitMQ restart.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(array $payload): void
    {
        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
        );

        $channel = $this->channel();
        $channel->queue_declare($this->queueName, false, true, false, false);
        $channel->basic_publish($message, '', $this->queueName);
    }

    /**
     * Memproses pesan satu per satu. Handler sukses → ack; handler melempar atau pesan bukan objek JSON → ditolak tanpa
     * dikembalikan ke antrean (data tetap ada di database dan dikirim ulang oleh perintah republish).
     *
     * @param callable(array<string, mixed>): void $handler
     * @param int                                  $idleTimeoutSeconds berhenti bila tidak ada pesan selama ini; 0 = berjalan terus
     */
    public function consume(callable $handler, int $idleTimeoutSeconds = 0): void
    {
        $channel = $this->channel();
        $channel->queue_declare($this->queueName, false, true, false, false);
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($this->queueName, '', false, false, false, false, function (AMQPMessage $message) use ($handler): void {
            $this->handle($message, $handler);
        });

        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, $idleTimeoutSeconds);
            } catch (AMQPTimeoutException) {
                return;
            }
        }
    }

    protected function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
            $this->channel    = $this->connection->channel();
        }

        return $this->channel;
    }

    private function handle(AMQPMessage $message, callable $handler): void
    {
        $payload = json_decode($message->getBody(), true);

        if (! is_array($payload)) {
            log_message('error', 'RabbitMQ: pesan bukan objek JSON dibuang.');
            $message->nack(false);

            return;
        }

        try {
            $handler($payload);
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ: handler gagal: ' . $e->getMessage());
            $message->nack(false);

            return;
        }

        $message->ack();
    }
}
