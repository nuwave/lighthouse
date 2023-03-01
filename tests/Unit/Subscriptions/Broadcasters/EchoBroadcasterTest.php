<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\EchoBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Events\EchoSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\TestsSubscriptions;

final class EchoBroadcasterTest extends TestCase
{
    use TestsSubscriptions;

    public function testBroadcast(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $broadcastManager->expects($this->once())
            ->method('event')
            ->with(new Callback(static fn (EchoSubscriptionEvent $event) => Broadcaster::EVENT_NAME === $event->broadcastAs()
                && 'test-123' === $event->broadcastOn()->name
                && 'foo' === $event->data));

        $redisBroadcaster = new EchoBroadcaster($broadcastManager);
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'test-123';

        $redisBroadcaster->broadcast($subscriber, 'foo');
    }

    public function testBroadcastChannelNameIsNotModified(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $broadcastManager->expects($this->once())
            ->method('event')
            ->with(new Callback(static fn (EchoSubscriptionEvent $event) => 'private-test-123' === $event->broadcastOn()->name));

        $redisBroadcaster = new EchoBroadcaster($broadcastManager);
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'private-test-123';

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
        $this->assertSame(md5('abcdef'), $data->channel_data->user_id);
        $this->assertSame(200, $response->status());
    }

    public function testUnauthorized(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $redisBroadcaster = new EchoBroadcaster($broadcastManager);

        $response = $redisBroadcaster->unauthorized(new Request());
        $this->assertSame(403, $response->getStatusCode());
    }
}
