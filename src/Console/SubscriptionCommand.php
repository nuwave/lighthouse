<?php

namespace Nuwave\Lighthouse\Console;

class SubscriptionCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:subscription';

    protected $description = 'Create a class for a single field on the root Subscription type.';

    protected $type = 'Subscription';

    protected function namespaceConfigKey(): string
    {
        return 'subscriptions';
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/subscription.stub';
    }
}
