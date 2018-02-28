<?php

namespace Nuwave\Lighthouse\Tests\Unit\Schema\Resolvers;

use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Tests\TestCase;

class ScalarResolverTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveScalarType()
    {
        $schema = $this->parse('
        # Email address scalar
        scalar Email @scalar(class: "Email")
        ');

        $scalar = collect($schema->definitions)->filter(function ($def) {
            return $def instanceof ScalarTypeDefinitionNode;
        })->map(function (ScalarTypeDefinitionNode $enum) {
            return ScalarResolver::resolve($enum);
        })->first();

        $this->assertInstanceOf(ScalarType::class, $scalar);
        $this->assertEquals('Email', $scalar->name);
        $this->assertEquals('Email address.', $scalar->description);
    }
}
