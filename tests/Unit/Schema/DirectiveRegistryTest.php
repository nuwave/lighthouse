<?php

namespace Tests\Unit\Schema;

use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Directives\Fields\AbstractFieldDirective;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\Types\ScalarDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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
            graphql()->directives()->get(ScalarDirective::name())
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

        $scalarResolver = graphql()->directives()->typeResolver($definition);
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
        $fooDirective = new class() implements Directive {
            public static function name()
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
            new class() extends AbstractFieldDirective implements FieldMiddleware {
                public static function name()
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

        $originalDefinition = new class() implements FieldMiddleware {
            public static function name()
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
