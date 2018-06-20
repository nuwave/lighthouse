<?php

namespace Tests\Unit\Schema;

use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Fields\BaseFieldDirective;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class DirectiveRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(
            ScalarDirective::class,
            graphql()->directives()->get((new ScalarDirective())->name())
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

        $scalarResolver = graphql()->directives()->nodeResolver($definition);
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

        $resolver = graphql()->directives()->fieldResolver($fieldDefinition);
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

        graphql()->directives()->fieldResolver($fieldDefinition);
    }

    /**
     * @test
     */
    public function itCanGetCollectionOfFieldMiddleware()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            bar: String @can(if: ["viewBar"]) @event
        ');

        $middleware = graphql()->directives()->fieldMiddleware($fieldDefinition);
        $this->assertCount(2, $middleware);
    }

    /**
     * @test
     */
    public function itCanRegisterDirectivesDirectly()
    {
        $fooDirective = new class() implements Directive
        {
            public function name()
            {
                return 'foo';
            }
        };

        graphql()->directives()->register($fooDirective);
        $this->assertSame($fooDirective, graphql()->directives()->get('foo'));
    }

    /**
     * @test
     */
    public function itHydratesAbstractFieldDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        graphql()->directives()->register(
            new class() extends BaseFieldDirective implements FieldMiddleware
            {
                public function name()
                {
                    return 'foo';
                }

                public function getFieldDefinition()
                {
                    return $this->fieldDefinition;
                }

                public function handleField(FieldValue $value)
                {
                }
            }
        );

        $fooDirective = graphql()->directives()->fieldMiddleware($fieldDefinition)->first();
        $this->assertSame($fieldDefinition, $fooDirective->getFieldDefinition());
    }

    /**
     * @deprecated this test is for compatibility reasons and can likely be removed in v3
     * @test
     */
    public function itDoesAllowNonAbstractFieldDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        $originalDefinition = new class() implements FieldMiddleware
        {
            public function name()
            {
                return 'foo';
            }

            public function handleField(FieldValue $value)
            {
            }
        };
        graphql()->directives()->register($originalDefinition);

        $fromRegistry = graphql()->directives()->fieldMiddleware($fieldDefinition)->first();
        $this->assertSame($originalDefinition, $fromRegistry);
    }
}
