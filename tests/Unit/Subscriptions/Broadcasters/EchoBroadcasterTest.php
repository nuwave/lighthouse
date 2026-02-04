<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Broadcasters;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\EchoBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Subscriptions\Events\EchoSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use PHPUnit\Framework\Constraint\Callback;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;

final class EchoBroadcasterTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    public function testBroadcast(): void
    {
        $broadcastManager = $this->createMock(BroadcastManager::class);
        $broadcastManager->expects($this->once())
            ->method('event')
            ->with(new Callback(static fn (EchoSubscriptionEvent $event): bool => $event->broadcastAs() === Broadcaster::EVENT_NAME
                && $event->broadcastOn()->name === 'test-123'
                && $event->data === 'foo'));

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
            ->with(new Callback(
                static fn (EchoSubscriptionEvent $event): bool => $event->broadcastOn()->name === 'private-test-123',
            ));

        $redisBroadcaster = new EchoBroadcaster($broadcastManager);
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = 'private-test-123';

        $redisBroadcaster->broadcast($subscriber, 'foo');
    }

    public function testAuthorized(): void
    {
        $redisBroadcaster = new EchoBroadcaster($this->createMock(BroadcastManager::class));

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
        $redisBroadcaster = new EchoBroadcaster($this->createMock(BroadcastManager::class));

        $response = $redisBroadcaster->unauthorized(new Request());
        $this->assertSame(403, $response->getStatusCode());
    }
}
