<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use OpenSwoole\Constant as OpenSwooleConstant;
use OpenSwoole\Http\Request;
use OpenSwoole\Server as OpenSwooleServer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;

class SubscriptionsServeCommand extends Command
{
    protected $signature = 'lighthouse:subscriptions:serve {--H|host=0.0.0.0} {--p|port=9502} {--d|debug}';

    protected $description = 'Starts a websocket server for graphql-ws clients to connect to.';

    protected $subscribers = [];

    public function handle()
    {
        // todo wss
        $server = new Server($this->option('host'), $this->option('port')/*, OpenSwooleServer::SIMPLE_MODE, OpenSwooleConstant::SOCK_TCP | OpenSwooleConstant::SSL */);

        $server->set([
            'websocket_subprotocol' => 'graphql-transport-ws',
            // todo cert paths
        ]);

        $server->on('start', function (Server $server) {
            $this->info(sprintf('Started listening on %s://%s:%s', 'ws', $server->host, $server->port));
        });

        $server->on('open', function (Server $server, Request $request) {
            $this->debug('Connection opened.', $request);
        });

        $server->on('message', function (Server $server, Frame $frame) {
            $this->debug('Received message.', $frame);
            $payload = \Safe\json_decode($frame->data);
            match($payload->type) {
                'connection_init' => $this->handleConnectionInit($server, $frame, $payload),
                // todo ping pong
                default => throw new \RuntimeException('Unknown message type: ' . $payload->type),
            };

            if ($payload->type === 'subscribe') {
                $payload->id;
                $server->disconnect($frame->fd, 4409, "Subscriber for {$operationId} already exists.");

                // create a subscriber

                $server->push($frame->fd, \Safe\json_encode([
                    'id' => $payload->id,
                    'type' => 'next',
                    'payload' => [
                        //
                    ],
                ]));
            }
        });

        $server->on('close', function (Server $server, int $fd) {
            $this->info("connection close: {$fd}");
        });

        $server->on('disconnect', function (Server $server, int $fd) {
            $this->info("connection disconnect: {$fd}");
        });

        Redis::psubscribe('sub_prefix:*', $this->getSubscriptionHandler($server));

        $server->start();
    }

    private function debug(string $message, $data): void
    {
        if ($this->option('debug')) {
            $this->info('DEBUG: ' . $message . ' ' . \Safe\json_encode($data));
        }
    }

    private function handleConnectionInit(Server $server, Frame $frame, object $payload)
    {
        if (array_key_exists($frame->fd, $this->subscribers)) {
            $server->disconnect($frame->fd, 4429, 'Too many initialisation requests.');
            $this->debug('Disconnected client, because it already was connected.', $frame);
            return;
        }
        // todo authorization
        $this->subscribers[$frame->fd] = null;
        $server->push($frame->fd, \Safe\json_encode([
            'type' => 'connection_ack',
        ]));

    }

    private function getSubscriptionHandler(Server $server)
    {
        return function (string $message, string $channel) use ($server) {
            $subscriptionName = Str::after($channel, 'sub_prefix:');
            $value = unserialize($message);
            // get subscription
            // go through all subscribers
            // filter stuff
            $server->push(123, \Safe\json_encode([
                'id' => 1234,
                'type' => 'next',
                'payload' => [
                    //
                ],
            ]));
        };
    }
}
