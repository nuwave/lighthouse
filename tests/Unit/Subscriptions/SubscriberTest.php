<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use GraphQL\Language\AST\OperationDefinitionNode;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Execution\HttpGraphQLContext;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;
use Tests\TestsSerialization;

final class SubscriberTest extends TestCase
{
    use TestsSerialization;
    use EnablesSubscriptionServiceProvider;

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

        $context = new HttpGraphQLContext(new Request());

        $subscriber = new Subscriber($args, $context, $resolveInfo);

        $topic = 'topic';
        $subscriber->topic = $topic;

        $channel = $subscriber->channel;

        $serialized = unserialize(serialize($subscriber));

        $this->assertInstanceOf(Subscriber::class, $serialized);
        $this->assertSame($args, $serialized->args);
        $this->assertSame($channel, $serialized->channel);
        $this->assertSame($topic, $serialized->topic);
        $this->assertSame($fieldName, $serialized->fieldName);
    }

    public function testEncryptedChannels(): void
    {
        $args = ['foo' => 'bar'];

        $resolveInfo = $this->createMock(ResolveInfo::class);
        $fieldName = 'baz';
        $resolveInfo->fieldName = $fieldName;

        $resolveInfo->operation = new OperationDefinitionNode([]);
        $resolveInfo->fragments = [];

        $context = new HttpGraphQLContext(new Request());

        $config = $this->app->make(ConfigRepository::class);

        $config->set('lighthouse.subscriptions.encrypted_channels', true);

        $encryptedSubscriber = new Subscriber($args, $context, $resolveInfo);

        $topic = 'topic';
        $encryptedSubscriber->topic = $topic;

        $channel = $encryptedSubscriber->channel;

        $this->assertStringStartsWith('private-encrypted-lighthouse-', $channel);

        $config->set('lighthouse.subscriptions.encrypted_channels', false);

        $encryptedSubscriber = new Subscriber($args, $context, $resolveInfo);

        $topic = 'topic';
        $encryptedSubscriber->topic = $topic;

        $channel = $encryptedSubscriber->channel;

        $this->assertStringStartsWith('private-lighthouse-', $channel);
    }
}
