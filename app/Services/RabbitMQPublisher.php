<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    public function publish(string $queue, string $notificationId): void
    {
        $connection = new AMQPStreamConnection(
            host: config('queue.connections.rabbitmq.host', 'rabbitmq'),
            port: config('queue.connections.rabbitmq.port', 5672),
            user: config('queue.connections.rabbitmq.user', 'guest'),
            password: config('queue.connections.rabbitmq.password', 'guest'),
            vhost: config('queue.connections.rabbitmq.vhost', '/'),
        );

        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        $msg = new AMQPMessage(json_encode(['notification_id' => $notificationId]), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $channel->basic_publish($msg, '', $queue);
        $channel->close();
        $connection->close();
    }
}
