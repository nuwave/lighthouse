<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;
use Tests\Unit\Execution\Fixtures\FooContext;

final class CreatesContextTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(CreatesContext::class, static fn (): CreatesContext => new class() implements CreatesContext {
            public function generate(?Request $request): GraphQLContext
            {
                return new FooContext();
            }
        });
    }

    public function testGenerateCustomContext(): void
    {
        $this->mockResolver(static function (mixed $root, array $args, GraphQLContext $context): string {
            \PHPUnit\Framework\Assert::assertInstanceOf(FooContext::class, $context);

            return $context->foo();
        });

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
