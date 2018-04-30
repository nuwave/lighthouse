<?php
namespace Nuwave\Lighthouse\Support\Broadcaster;

use Nuwave\Lighthouse\Support\WebSockets\Protocol;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use WebSocket\Client;

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
    public function broadcast(array $class, $subscription, array $params = [])
    {   
        $url = 'ws://' . (app('config')['broadcasting.connections.graphql.url'] ?: '127.0.0.1' . ':' . app('config')['broadcasting.connections.graphql.port']);

        $class = $this->formatChannels($class)[0];

        $client = new Client($url, ['headers' => ['Sec-WebSocket-Protocol' => 'graphql-ws']]);
        $request = [
            'type'         => Protocol::GQL_DATA,
            'subscription' => $subscription,
            'payload'      => ['class' => $class, 'params' => $params],
        ];
        $client->send(json_encode($request));
    }
}
