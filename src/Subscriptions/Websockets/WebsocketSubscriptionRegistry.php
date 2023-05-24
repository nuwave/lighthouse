<?php

namespace Nuwave\Lighthouse\Subscriptions\Websockets;

use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class WebsocketSubscriptionRegistry extends SubscriptionRegistry
{
    private ?string $nextId = null;

    public function setNextId(string $nextId)
    {
        $this->nextId = $nextId;
    }

    public function subscriber(Subscriber $subscriber, string $topic): self
    {
        if (empty($this->nextId)) {
            throw new \RuntimeException('Next subscriber id is empty!');
        }

        $this->subscribers[$topic][$this->nextId] = $subscriber;

        return $this;
    }

    public function handleStartExecution(StartExecution $startExecution): void
    {
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse
    {
        return null;
    }

    public function getSubscribers(): array
    {
        return $this->subscribers;
    }
}
