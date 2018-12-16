<?php

namespace Tests\Integration\Subscriptions;

use GraphQL\Language\Parser;
use Tests\Utils\Models\User;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

trait HandlesSubscribers
{
    protected function subscriber($queryString = null): Subscriber
    {
        $queryString = $queryString ?: '{ me }';
        $document = Parser::parse('subscription foo '.$queryString);
        $resolveInfo = new ResolveInfo([
            'operation' => $document->definitions[0],
        ]);

        return new Subscriber(
            ['foo' => 'bar'],
            $this,
            $resolveInfo
        );
    }

    public function user(): User
    {
        return new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@doe.com',
        ]);
    }

    public function request()
    {
        return null;
    }
}
