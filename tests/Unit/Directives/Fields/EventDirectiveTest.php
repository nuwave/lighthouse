<?php


namespace Tests\Unit\Directives\Fields;


use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Tests\TestCase;

class EventDirectiveTest extends TestCase
{
    /** @test */
    public function can_fire_event()
    {
        Event::fake();

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

        $schema = graphql()->build($schema);

        $userField = $schema->type('Query')->field('user');
        $resolver = $userField->resolver(new ResolveInfo($userField));



        $resolver();

        Event::assertDispatched(FetchedUser::class);
    }
}

class FetchedUser{}