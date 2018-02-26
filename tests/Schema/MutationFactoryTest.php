<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\MutationFactory;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Nuwave\Lighthouse\Tests\TestCase;

class MutationFactoryTest extends TestCase
{
    /**
     * Mutation arguments.
     *
     * @var array
     */
    protected $args = ['bar' => 'foo', 'baz' => 1];

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->app['config']->set(
            'lighthouse.namespaces.mutations',
            'Nuwave\\Lighthouse\\Tests\\Utils\\Mutations'
        );
    }

    /**
     * @test
     */
    public function itCanAutoResolveAMutation()
    {
        $schema = Parser::parse('
        type Mutation {
            foo(bar: String! baz: Int ): String!
        }
        ');

        $type = MutationFactory::resolve($schema->definitions[0]->fields[0]);
        $this->assertArrayHasKey('args', $type);
        $this->assertArrayHasKey('type', $type);
        $this->assertArrayHasKey('resolve', $type);

        $this->assertInstanceOf(\Closure::class, $type['resolve']);
        $this->assertEquals('foo 1', $type['resolve'](null, $this->args));
    }

    /**
     * @test
     * @group failing
     */
    public function itCanValidateMutationInput()
    {
        $schema = Parser::parse('
        type Mutation {
            foo(bar: String! @validate(rules:["min:4"]) baz: Int @validate(rules:["min:2"])): String!
        }
        ');

        $this->expectException(ValidationError::class);
        $type = MutationFactory::resolve($schema->definitions[0]->fields[0]);
        $type['resolve'](null, $this->args);
    }
}
