<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\DriverManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorage;
use Nuwave\Lighthouse\Subscriptions\Storage\MemoryStorage;
use Nuwave\Lighthouse\Subscriptions\Storage\DatabaseStorage;

/**
 * @method Subscriber|null                subscriberByChannel(string $channel)
 * @method \Illuminate\Support\Collection subscriberByTopic($topic)
 * @method void                           storeSubscriber(Subscriber $subscriber, string $topic)
 * @method Subscriber|null                deleteSubscriber(string $channel)
 */
class StorageManager extends DriverManager
{
    /**
     * Get configuration key.
     *
     * @return string
     */
    protected function configKey()
    {
        return 'lighthouse.subscriptions.stores';
    }

    /**
     * Get configuration driver key.
     *
     * @return string
     */
    protected function driverKey()
    {
        return 'lighthouse.subscriptions.storage';
    }

    /**
     * Create instance of memory storage.
     *
     * @return MemoryStorage
     */
    protected function createMemoryDriver()
    {
        return new MemoryStorage();
    }

    /**
     * Create instance of database storage.
     *
     * @return DatabaseStorage
     */
    protected function createDatabaseStorage()
    {
        return new DatabaseStorage();
    }

    /**
     * Create instance of redis storage.
     *
     * @return RedisStorage
     */
    protected function createRedisStorage()
    {
        return new RedisStorage();
    }
}
