<?php


namespace Tests\Unit\Directives\Fields;


use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Testing\Fakes\EventFake;
use Mockery;
use Nuwave\Lighthouse\Schema\Directives\Fields\EventDirective;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Tests\TestCase;

class EventDirectiveTest extends TestCase
{
    public function testCanFireEvent()
    {
        $schema = '
        type User {
            id: ID!
            name: String!
            email: String!
        }
        type Query {
            user: User @event(fire: "'.addslashes(FetchedUser::class).'")
        }
        ';

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->expects('dispatch')->with(Mockery::type(FetchedUser::class));

        $this->graphql->directives()->add(new EventDirective($dispatcher));
        $schema = $this->graphql->build($schema);

        $userField = $schema->type('Query')->field('user');
        $resolver = $userField->resolver(new ResolveInfo($userField));

        $resolver();
        $this->assertTrue(true);
    }
}

class FetchedUser{}
