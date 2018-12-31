<?php

namespace Tests\Integration\Subscriptions;

use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait HandlesSubscribers
{
    /**
     * Construct a dummy subscriber for testing.
     *
     * @param string|null $queryString
     *
     * @return Subscriber
     */
    protected function subscriber(?string $queryString = null): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->args = ['foo' => 'bar'];
        $subscriber->context = $this;
        $subscriber->operationName = 'foo';
        $subscriber->queryString = $queryString ?: '{ me }';
        $subscriber->channel = Subscriber::uniqueChannelName();

        return $subscriber;
    }

    /**
     * @see GraphQLContext::user()
     *
     * @return User
     */
    public function user(): User
    {
        return new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@doe.com',
        ]);
    }

    /**
     * @see GraphQLContext::request()
     *
     * @return null
     */
    public function request()
    {
    }
}
