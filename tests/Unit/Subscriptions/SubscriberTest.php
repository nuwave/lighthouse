<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use GraphQL\Language\AST\OperationDefinitionNode;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsSerialization;
use Tests\TestsSubscriptions;

final class SubscriberTest extends TestCase
{
    use TestsSerialization;
    use TestsSubscriptions;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->useSerializingArrayStore();
        $this->fakeContextSerializer();
    }

    public function testSerializable(): void
    {
        $args = ['foo' => 'bar'];

        $resolveInfo = $this->createMock(ResolveInfo::class);
        $fieldName = 'baz';
        $resolveInfo->fieldName = $fieldName;
        $resolveInfo->operation = new OperationDefinitionNode([]);
        $resolveInfo->fragments = [];
        $context = new Context(new Request());

        $subscriber = new Subscriber($args, $context, $resolveInfo);
        $topic = 'topic';
        $subscriber->topic = $topic;

        $serialized = unserialize(serialize($subscriber));

        assert($serialized instanceof Subscriber);
        $this->assertSame($args, $serialized->args);
        $this->assertNotNull($serialized->channel);
        $this->assertSame($topic, $serialized->topic);
        $this->assertSame($fieldName, $serialized->fieldName);
    }
}
