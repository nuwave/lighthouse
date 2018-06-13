<?php

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\Types\ScalarDirective;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class DirectiveRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(ScalarDirective::class, graphql()->directives()->get(ScalarDirective::name()));
    }

    /**
     * @test
     */
    public function itGetsLighthouseHandlerForScalar()
    {
        $schema = 'scalar Email @scalar(class: "Email")';
        $document = Parser::parse($schema);
        $definition = $document->definitions[0];
        $scalar = graphql()->directives()->typeResolver($definition)
            ->resolveType(new TypeValue($definition));

        $this->assertInstanceOf(ScalarType::class, $scalar);
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
    public function itThrowsExceptionsWhenMultipleFieldResolverDirectives()
    {
        $this->expectException(DirectiveException::class);

        $schema = '
        type Foo {
            bar: [Bar!]! @hasMany @hasMany
        }
        ';

        $document = Parser::parse($schema);
        graphql()->directives()->fieldResolver($document->definitions[0]->fields[0]);
    }

    /**
     * @test
     */
    public function itCanGetCollectionOfFieldMiddleware()
    {
        $schema = '
        type Foo {
            bar: String @can(if: ["viewBar"]) @event
        }
        ';

        $document = Parser::parse($schema);
        $middleware = graphql()->directives()->fieldMiddleware($document->definitions[0]->fields[0]);
        $this->assertCount(2, $middleware);
    }
}
