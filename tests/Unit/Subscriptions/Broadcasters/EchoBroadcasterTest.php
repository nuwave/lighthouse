<?php

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\EchoBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Events\EchoSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\TestsSubscriptions;

class EchoBroadcasterTest extends TestCase
{
    use TestsSubscriptions;

    public function testBroadcast(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $broadcastManager->expects($this->once())
            ->method('event')
            ->with(new Callback(function (EchoSubscriptionEvent $event) {
                return $event->broadcastAs() === 'lighthouse.subscription' &&
                    $event->broadcastOn()->name === 'presence-test-123' &&
                    $event->data === 'foo';
            }));

        $redisBroadcaster = new EchoBroadcaster($broadcastManager);
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $redisBroadcaster->broadcast($subscriber, 'foo');
    }

    public function testAuthorized(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $redisBroadcaster = new EchoBroadcaster($broadcastManager);

        $request = new Request();
        $request['channel_name'] = 'abc';
        $request['socket_id'] = 'def';

        $response = $redisBroadcaster->authorized($request);
        $data = \Safe\json_decode($response->content());
        $this->assertEquals(md5('abcdef'), $data->channel_data->user_id);
        $this->assertEquals(200, $response->status());
    }

    public function testUnauthorized(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $redisBroadcaster = new EchoBroadcaster($broadcastManager);

        $response = $redisBroadcaster->unauthorized(new Request());
        $this->assertEquals(403, $response->getStatusCode());
    }
}
