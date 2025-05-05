<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConsumeRabbitMQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ queue';

    public function handle()
    {
        $remote_url = env('APP_REMOTE_URL');
        // Load configuration from config/rabbitmq.php
        $config = config('rabbitmq');

        $host = $config['host'] ?? env('RABBITMQ_HOST', 'localhost');
        $port = $config['port'] ?? env('RABBITMQ_PORT', 5672);
        $user = $config['user'] ?? env('RABBITMQ_USER', 'guest');
        $password = $config['password'] ?? env('RABBITMQ_PASSWORD', 'guest');
        $vhost = $config['vhost'] ?? env('RABBITMQ_VHOST', '/');
        $queue = $config['queue'] ?? env('RABBITMQ_QUEUE', 'default');

        // Establish connection (no SSL for standard AMQP)
        $connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost
        );

        $channel = $connection->channel();

        // Declare the queue (durable = true)
        $channel->queue_declare($queue, false, false, false, false);

        $this->info("Waiting for messages on queue [$queue]. To exit press CTRL+C");

        // Define message processing callback
        $callback = function (AMQPMessage $msg) {
            $this->info('Received: ' . $msg->body);

            // Process the message
            $data = json_decode($msg->body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = Http::timeout(30)->post('{$remote_url}/api/wallet/wallet', [
                    'userId' => $data['userId'],
                    'username' => $data['username'],
                ]);
                
                // You can log the response to debug further
                \Log::info('Wallet creation response', ['response' => $response->body()]);
                
         
            } else {
                $this->error('Invalid JSON message received.');
            }

            // Acknowledge the message
            $msg->getChannel()->basic_ack($msg->getDeliveryTag());
        };

        // Prefetch 1 message at a time
        $channel->basic_qos(null, 1, null);

        // Start consuming messages
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        // Keep script running
        while ($channel->is_consuming()) {
            $channel->wait();
        }

        // Clean up
        $channel->close();
        $connection->close();
    }
}
