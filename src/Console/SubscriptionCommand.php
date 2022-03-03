<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\RootType;

class SubscriptionCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:subscription';

    protected $description = 'Create a class for a single field on the root Subscription type.';

    protected $type;

    public function __construct(Filesystem $files)
    {
        $this->type = RootType::Subscription();

        parent::__construct($files);
    }

    protected function namespaceConfigKey(): string
    {
        return 'subscriptions';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/subscription.stub';
    }
}
