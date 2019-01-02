<?php

namespace Tests\Unit\Schema\Execution;

use Tests\TestCase;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContextFactoryTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(CreatesContext::class, function () {
            return new class() implements CreatesContext {
                public function generate(Request $request)
                {
                    return new class($request) implements GraphQLContext {
                        public function __construct($request)
                        {
                            $this->request = $request;
                        }

                        public function user()
                        {
                        }

                        public function request()
                        {
                            return $this->request;
                        }

                        public function foo()
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
    public function itCanGenerateCustomContext()
    {
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type Query {
            context: String @field(resolver:\"{$resolver}\")
        }
        ";
        $query = '
        {
            context
        }
        ';

        $this->query($query)->assertJson([
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
