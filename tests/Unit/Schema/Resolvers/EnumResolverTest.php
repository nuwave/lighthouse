<?php

namespace Nuwave\Lighthouse\Tests\Unit\Schema\Resolvers;

use GraphQL\Language\Parser;

use Nuwave\Lighthouse\Schema\Resolvers\EnumResolver;
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
        $schema = $this->parse('
        enum Role {
            # Admin user type.
            ADMIN @enum(value: "admin")
            # Employee user type.
            EMPLOYEE @enum(value: "employee")
        }
        ');

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
