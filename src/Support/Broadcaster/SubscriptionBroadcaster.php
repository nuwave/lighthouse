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
    public function broadcast(array $channels, $event, array $payload = [])
    {   
        $url = 'ws://' . (app('config')['broadcasting.connections.graphql.url'] ?: '127.0.0.1' . ':' . app('config')['broadcasting.connections.graphql.port']);

        $client = new Client($url, ['headers' => ['Sec-WebSocket-Protocol' => 'graphql-ws']]);
        foreach ($this->formatChannels($channels) as $channel) {
             $request = [
                'type'         => Protocol::GQL_DATA,
                'subscription' => $channel,
                'payload'      => ['class' => $event, 'params' => $payload],
            ];
            $client->send(json_encode($request));
        }       
    }
}
