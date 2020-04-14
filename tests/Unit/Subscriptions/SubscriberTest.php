<?php

namespace Tests\Unit\Subscriptions;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsSerialization;

class SubscriberTest extends TestCase
{
    use TestsSerialization;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->useSerializingArrayStore($app);
        $this->fakeContextSerializer($app);
    }

    public function testSerializable(): void
    {
        $args = ['foo' => 'bar'];

        $resolveInfo = $this->getMockBuilder(ResolveInfo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $operationName = 'baz';
        $resolveInfo->operation = (object) [
            'name' => (object) [
                'value' => $operationName
            ]
        ];
        $resolveInfo->fragments = [];
        $context = new Context(new Request());

        $subscriber = new Subscriber($args, $context, $resolveInfo);
        $topic = 'topic';
        $subscriber->topic = $topic;

        /** @var \Nuwave\Lighthouse\Subscriptions\Subscriber $serialized */
        $serialized = unserialize(serialize($subscriber));

        $this->assertInstanceOf(Subscriber::class, $serialized);
        $this->assertSame($args, $serialized->args);
        $this->assertNotNull($serialized->channel);
        $this->assertSame($topic, $serialized->topic);
        $this->assertSame($operationName, $serialized->operationName);
    }
}
