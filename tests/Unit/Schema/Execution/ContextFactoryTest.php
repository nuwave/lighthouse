<?php

namespace Tests\Unit\Schema\Execution;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;
use Tests\Unit\Schema\Execution\Fixtures\FooContext;

class ContextFactoryTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(CreatesContext::class, function (): CreatesContext {
            return new class implements CreatesContext {
                public function generate(Request $request): GraphQLContext
                {
                    return new FooContext($request);
                }
            };
        });
    }

    public function testCanGenerateCustomContext(): void
    {
        $this->mockResolver(
            function ($root, array $args, GraphQLContext $context): string {
                /** @var FooContext $context */
                return $context->foo();
            }
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            context: String @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            context
        }
        ')->assertJson([
            'data' => [
                'context' => FooContext::FROM_FOO_CONTEXT,
            ],
        ]);
    }
}
