<?php

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Schema\Factories\MutationFactory;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Tests\TestCase;
use Tests\Utils\Events\Foo;

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
            'Tests\\Utils\\Mutations'
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

        $type = MutationFactory::resolve(
            $schema->definitions[0]->fields[0],
            $schema->definitions[0]
        );
        $this->assertArrayHasKey('args', $type);
        $this->assertArrayHasKey('type', $type);
        $this->assertArrayHasKey('resolve', $type);

        $this->assertInstanceOf(\Closure::class, $type['resolve']);
        $this->assertEquals('foo 1', $type['resolve'](null, $this->args));
    }

    /**
     * @test
     */
    public function itCanValidateMutationInput()
    {
        $schema = Parser::parse('
        type Mutation {
            foo(bar: String! @validate(rules:["min:4"]) baz: Int @validate(rules:["min:2", "max:8"])): String!
        }
        ');

        $this->expectException(ValidationError::class);
        $type = MutationFactory::resolve(
            $schema->definitions[0]->fields[0],
            $schema->definitions[0]
        );
        $type['resolve'](null, $this->args);
    }

    /**
     * @test
     */
    public function itCanResolveCustomNamespace()
    {
        $schema = Parser::parse('
        type Mutation {
            foo(bar: String! baz: Int): String! @mutation(class:"Tests\\\Utils\\\Mutations\\\Bar")
        }
        ');

        $type = MutationFactory::resolve(
            $schema->definitions[0]->fields[0],
            $schema->definitions[0]
        );
        $this->assertArrayHasKey('args', $type);
        $this->assertArrayHasKey('type', $type);
        $this->assertArrayHasKey('resolve', $type);

        $this->assertInstanceOf(\Closure::class, $type['resolve']);
        $this->assertEquals('1 foo', $type['resolve'](null, $this->args));
    }

    /**
     * @test
     */
    public function itCanFireAnEventAfterAMutation()
    {
        $expected = 'foo 1';
        $schema = Parser::parse('
        type Mutation {
            foo(bar: String! baz: Int ): String! @event(class:"Tests\\\Utils\\\Events\\\Foo")
        }
        ');

        Event::fake();

        $type = MutationFactory::resolve(
            $schema->definitions[0]->fields[0],
            $schema->definitions[0]
        );
        $this->assertArrayHasKey('args', $type);
        $this->assertArrayHasKey('type', $type);
        $this->assertArrayHasKey('resolve', $type);
        $this->assertInstanceOf(\Closure::class, $type['resolve']);

        $assert = $type['resolve'](null, $this->args);

        $this->assertEquals($expected, $assert);
        Event::assertDispatched(Foo::class, function ($e) use ($expected) {
            return $e->value === $expected;
        });
    }
}
