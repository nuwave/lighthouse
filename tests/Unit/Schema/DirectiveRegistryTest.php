<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;

class DirectiveRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(
            ScalarDirective::class,
            app(DirectiveRegistry::class)->get((new ScalarDirective())->name())
        );
    }

    /**
     * @test
     */
    public function itGetsLighthouseHandlerForScalar()
    {
        $definition = PartialParser::scalarTypeDefinition('
            scalar Email @scalar(class: "Email")
        ');

        $scalarResolver = app(DirectiveRegistry::class)->nodeResolver($definition);
        $this->assertInstanceOf(ScalarDirective::class, $scalarResolver);
    }

    /**
     * @test
     */
    public function itThrowsErrorIfMultipleDirectivesAssignedToNode()
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchemaWithDefaultQuery('
            scalar DateTime @scalar @foo
        ');
    }

    /**
     * @test
     */
    public function itCanGetFieldResolverDirective()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: [Foo!]! @hasMany
        ');

        $resolver = app(DirectiveRegistry::class)->fieldResolver($fieldDefinition);
        $this->assertInstanceOf(FieldResolver::class, $resolver);
    }

    /**
     * @test
     */
    public function itThrowsExceptionWhenMultipleFieldResolverDirectives()
    {
        $this->expectException(DirectiveException::class);

        $fieldDefinition = PartialParser::fieldDefinition('
            bar: [Bar!]! @hasMany @belongsTo
        ');

        app(DirectiveRegistry::class)->fieldResolver($fieldDefinition);
    }

    /**
     * @test
     */
    public function itCanGetCollectionOfFieldMiddleware()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            bar: String @can(if: ["viewBar"]) @event
        ');

        $middleware = app(DirectiveRegistry::class)->fieldMiddleware($fieldDefinition);
        $this->assertCount(2, $middleware);
    }

    /**
     * @test
     */
    public function itCanRegisterDirectivesDirectly()
    {
        $fooDirective = new class() implements Directive {
            public function name()
            {
                return 'foo';
            }
        };

        app(DirectiveRegistry::class)->register($fooDirective);
        $this->assertEquals($fooDirective, graphql()->directives()->get('foo'));
        $this->assertNotSame($fooDirective, graphql()->directives()->get('foo'));
    }

    /**
     * @test
     */
    public function itHydratesBaseDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        app(DirectiveRegistry::class)->register(
            new class() extends BaseDirective implements FieldMiddleware {
                public function name()
                {
                    return 'foo';
                }

                public function getFieldDefinition()
                {
                    return $this->definitionNode;
                }

                public function handleField(FieldValue $value, \Closure $next)
                {
                }
            }
        );

        $fooDirective = app(DirectiveRegistry::class)->fieldMiddleware($fieldDefinition)->first();
        $this->assertSame($fieldDefinition, $fooDirective->getFieldDefinition());
    }

    /**
     * @deprecated this test is for compatibility reasons and can likely be removed in v3
     * @test
     */
    public function itSkipsHydrationForNonBaseDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        $originalDefinition = new class() implements FieldMiddleware {
            public function name()
            {
                return 'foo';
            }

            public function handleField(FieldValue $value, \Closure $next)
            {
            }
        };
        app(DirectiveRegistry::class)->register($originalDefinition);

        $fromRegistry = app(DirectiveRegistry::class)->fieldMiddleware($fieldDefinition)->first();
        $this->assertEquals($originalDefinition, $fromRegistry);
        $this->assertNotSame($originalDefinition, $fromRegistry);
    }
}
