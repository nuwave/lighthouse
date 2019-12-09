<?php

namespace Nuwave\Lighthouse\Console;

class SubscriptionCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for a single field on the root Subscription type.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Subscription';

    protected function namespaceConfigKey(): string
    {
        return 'subscriptions';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/subscription.stub';
    }
}
