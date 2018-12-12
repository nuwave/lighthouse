<?php

namespace Tests\Integration\Subscriptions;

use Tests\Utils\Models\User;
use GraphQL\Language\AST\NameNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use GraphQL\Language\AST\OperationDefinitionNode;

trait HandlesSubscribers
{
    protected function subscriber($queryString = null): Subscriber
    {
        $queryString = $queryString ?: '{ me }';
        $info = new ResolveInfo([
            'operation' => new OperationDefinitionNode([
                'name' => new NameNode([
                    'value' => 'foo',
                ]),
            ]),
        ]);

        return Subscriber::initialize(
            'root',
            ['foo' => 'bar'],
            $this,
            $info,
            $queryString
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
