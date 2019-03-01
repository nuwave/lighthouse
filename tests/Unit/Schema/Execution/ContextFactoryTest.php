<?php

namespace Tests\Unit\Schema\Execution;

use Tests\TestCase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContextFactoryTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(CreatesContext::class, function (): CreatesContext {
            return new class implements CreatesContext {
                public function generate(Request $request)
                {
                    return new class($request) implements GraphQLContext {
                        /**
                         * @var \Illuminate\Http\Request
                         */
                        protected $request;

                        public function __construct(Request $request)
                        {
                            $this->request = $request;
                        }

                        public function user(): void
                        {
                            //
                        }

                        public function request(): Request
                        {
                            return $this->request;
                        }

                        public function foo(): string
                        {
                            return 'custom.context';
                        }
                    };
                }
            };
        });
    }

    /**
     * @test
     */
    public function itCanGenerateCustomContext(): void
    {
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type Query {
            context: String @field(resolver:\"{$resolver}\")
        }
        ";

        $this->query('
        {
            context
        }
        ')->assertJson([
            'data' => [
                'context' => 'custom.context',
            ],
        ]);
    }

    public function resolve($root, array $args, GraphQLContext $context): string
    {
        return $context->foo();
    }
}
