<?php

namespace Nuwave\Lighthouse\Tests\Queries;

use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Mutations\UpdateEmailMutation;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Mutations\UpdateEmailRelayMutation;

class MutationTest extends TestCase
{
    use GlobalIdTrait;

    /**
     * @test
     */
    public function itCanResolveMutation()
    {
        $query = 'mutation UpdateUserEmail {
            updateEmail(id: "foo", email: "foo@bar.com") {
                email
            }
        }';

        $expected = [
            'updateEmail' => [
                'email' => 'foo@bar.com'
            ]
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->mutation('updateEmail', UpdateEmailMutation::class);

        $this->assertEquals(['data' => $expected], $this->executeQuery($query));
    }

    /**
     * @test
     * @group failing
     */
    public function itCanResolveRelayMutation()
    {
        $id = $this->encodeGlobalId(UserType::class, 'foo');

        $query = 'mutation UpdateUserEmailRelay($input: UpdateUserPasswordRelayInput!) {
            updateEmail(input: $input) {
                user {
                    email
                }
                clientMutationId
            }
        }';

        $expected = [
            'updateEmail' => [
                'user' => [
                    'email' => 'foo@bar.com'
                ],
                'clientMutationId' => 'abcde',
            ]
        ];

        $variables = [
            'input' => [
                'id' => $id,
                'email' => 'foo@bar.com',
                'clientMutationId' => 'abcde'
            ]
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->mutation('updateEmail', UpdateEmailRelayMutation::class);

        $this->assertEquals(['data' => $expected], $this->executeQuery($query, $variables));
    }
}
