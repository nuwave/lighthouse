<?php

namespace Tests\Integration\Subscriptions;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

trait HandlesSubscribers
{
    /**
     * Construct a dummy subscriber for testing.
     *
     * @param  string|null  $queryString
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber
     */
    protected function subscriber(?string $queryString = null): Subscriber
    {
        $subscriber = new Subscriber(
            ['foo' => 'bar'],
            $this,
            new ResolveInfo()
        );
        $subscriber->operationName = 'foo';
        $subscriber->queryString = $queryString ?: '{ me }';
        $subscriber->channel = Subscriber::uniqueChannelName();

        return $subscriber;
    }

    /**
     * @see \Nuwave\Lighthouse\Support\Contracts\GraphQLContext::user()
     *
     * @return \Tests\Utils\Models\User
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
     * @see \Nuwave\Lighthouse\Support\Contracts\GraphQLContext::request()
     *
     * @return \Illuminate\Http\Request
     */
    public function request(): Request
    {
        return new Request();
    }
}
