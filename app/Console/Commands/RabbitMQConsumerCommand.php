<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQConsumerCommand extends Command
{
    protected $signature = 'rabbitmq:consume';

    protected $description = 'Consume notifications from RabbitMQ queues';

    private const QUEUES = [
        'notifications_transactional' => ['priority' => 10],
        'notifications_marketing' => ['priority' => 0],
    ];

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();

        $this->declareQueues($channel);

        $callback = function (AMQPMessage $msg) use ($dispatcher) {
            $notificationId = $this->extractNotificationId($msg->getBody());

            if ($notificationId === null) {
                $msg->ack();
                return;
            }

            $notification = Notification::find($notificationId);

            if ($notification && !$notification->isFinalState()) {
                try {
                    $dispatcher->dispatch($notification);
                    $msg->ack();
                } catch (\Throwable $e) {
                    $msg->nack(false, true);
                }
            } else {
                $msg->ack();
            }
        };

        foreach (array_keys(self::QUEUES) as $queueName) {
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);
        }

        $this->info('Waiting for messages...');

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function extractNotificationId(string $body): ?string
    {
        $payload = json_decode($body, true);

        if (isset($payload['notification_id'])) {
            return $payload['notification_id'];
        }

        return null;
    }

    private function declareQueues(AMQPChannel $channel): void
    {
        foreach (self::QUEUES as $queueName => $config) {
            $channel->queue_declare(
                $queueName,
                false,
                true,
                false,
                false,
            );
        }
    }

    private function getConnection(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: config('queue.connections.rabbitmq.host', 'rabbitmq'),
            port: config('queue.connections.rabbitmq.port', 5672),
            user: config('queue.connections.rabbitmq.user', 'guest'),
            password: config('queue.connections.rabbitmq.password', 'guest'),
            vhost: config('queue.connections.rabbitmq.vhost', '/'),
        );
    }
}
