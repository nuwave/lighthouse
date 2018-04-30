<?php
namespace Nuwave\Lighthouse\Support\Broadcaster;

use Nuwave\Lighthouse\Support\WebSockets\Protocol;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use WebSocket\Client;

class SubscriptionBroadcaster implements Broadcaster
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
        $url = 'ws://' . (app('config')['broadcasting.connections.graphql.url'] ?: '127.0.0.1' . ':' . app('config')['broadcasting.connections.graphql.port']);

        $client = new Client($url);
        $request = [
                'type'         => Protocol::GQL_DATA,
                'subscription' => $event,
                'payload'      => serialize($payload),
            ];
        $client->send(json_encode($request));
    }
}
