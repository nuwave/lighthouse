<?php

namespace Nuwave\Lighthouse\Tests\Resolvers;

use Nuwave\Lighthouse\Resolvers\EnumResolver;
use Nuwave\Lighthouse\Tests\TestCase;

use GraphQL\Type\Definition\EnumType;

use GraphQL\Language\AST\EnumTypeDefinitionNode;

class EnumResolverTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = $this->parseSchema();

        $enum = collect($schema->definitions)->filter(function ($def) {
            return $def instanceof EnumTypeDefinitionNode;
        })->map(function (EnumTypeDefinitionNode $enum) {
            return EnumResolver::resolve($enum);
        })->first();

        $this->assertInstanceOf(EnumType::class, $enum);
        $this->assertEquals([
            'name' => 'Role',
            'values' => [
                'ADMIN' => [
                    'value' => 'admin',
                    'description' => 'Admin user type.'
                ],
                'EMPLOYEE' => [
                    'value' => 'employee',
                    'description' => 'Employee user type.'
                ]
            ]
        ], $enum->config);
    }
}
