<?php
namespace Nuwave\Lighthouse\Support\Broadcaster;

use Ratchet\Client;
use Ratchet\Client\WebSocket;
use Nuwave\Lighthouse\Support\WebSockets\Protocol;

class SubscriptionBroadcaster extends Broadcaster
{
    /**
     * {@inheritdoc}
     */
    public function auth($request)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function validAuthenticationResponse($request, $result)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
    	Client\connect('ws://' . app('config')['broadcasting.connections.graphql.url'] ?: '127.0.0.1' . ':' . app('config')['broadcasting.connections.graphql.port'], ['graphql-ws'])
            ->then(function (WebSocket $conn) use ($event, $payload) {
            $request = [
                'type'         => Protocol::GQL_DATA,
                'subscription' => $event,
                'payload'      => serialize($payload),
            ];
            $conn->send(json_encode($request));
            $conn->close();
        });
    }
}
