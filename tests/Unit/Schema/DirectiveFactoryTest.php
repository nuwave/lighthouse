<?php

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class DirectiveFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(ScalarDirective::class, graphql()->directives()->handler('scalar'));
    }

    /**
     * @test
     */
    public function itGetsLighthouseHandlerForScalar()
    {
        $schema = 'scalar Email @scalar(class: "Email")';
        $document = Parser::parse($schema);
        $definition = $document->definitions[0];
        $scalar = graphql()->directives()->forNode($definition)
            ->resolveNode(new NodeValue($definition))
            ->getType();

        $this->assertInstanceOf(ScalarType::class, $scalar);
    }

    /**
     * @test
     */
    public function itThrowsErrorIfMultipleDirectivesAssignedToNode()
    {
        $this->expectException(DirectiveException::class);

        $schema = 'scalar DateTime @scalar @foo';
        $document = Parser::parse($schema);
        $handler = graphql()->directives()->forNode($document->definitions[0]);
    }

    /**
     * @test
     */
    public function itCanCheckIfFieldHasAResolverDirective()
    {
        $type = PartialParser::objectTypeDefinition('
        type Foo {
            bar: [Bar!]! @hasMany
        }');

        $hasResolver = graphql()->directives()->hasResolver($type->fields[0]);
        $this->assertTrue($hasResolver);
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
