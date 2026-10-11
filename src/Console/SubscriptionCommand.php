<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Nuwave\Lighthouse\Schema\RootType;

class SubscriptionCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:subscription';

    protected $description = 'Create a resolver class for a single field on the root Subscription type.';

    protected $type = RootType::SUBSCRIPTION;

    protected function namespaceConfigKey(): string
    {
        return 'subscriptions';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/subscription.stub';
    }
}
