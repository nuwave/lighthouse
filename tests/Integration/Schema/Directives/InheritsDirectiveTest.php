<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

class InheritsDirectiveTest extends TestCase
{
    public function testCanInheritsTypes()
    {
        $schema = $this->buildSchema(/**@lang GraphQL */ '
            type ParentType {
                attribute_1: String
            }
            type ChildType @inherits(from: ParentType) {
                attribute_2: String
            }

            type Query {
                childtypeQuery: ChildType
            }');

        $childType = $schema->getType('ChildType');

        $this->assertNotNull($childType->getField('attribute_1'));
    }

    public function testIfItcanInheritFromThatInherited()
    {
        $schema = $this->buildSchema(/**@lang GraphQL */ '
            type GrandParentType {
                attribute_1: String
            }
            type ParentType @inherits(from: GrandParentType) {
                attribute_2: String
            }

            type ChildType @inherits(from: ParentType){
                attribute_3: String
            }

            type Query {
                childtypeQuery: ChildType
            }');

        $childType = $schema->getType('ChildType');

        $this->assertNotNull($childType->getField('attribute_1'));
        $this->assertNotNull($childType->getField('attribute_2'));
        $this->assertNotNull($childType->getField('attribute_3'));
    }

    public function testChildOverridesFields()
    {
        $schema = $this->buildSchema(/* @lang GraphQL */
            '
            type ParentType {
                attribute_1: String
                attribute_2: Int
            }
            type ChildType @inherits(from: ParentType) {
                attribute_1: Int
                attribute_2: String
            }

            type Query {
                childtypeQuery: ChildType
            }');

        $childType = $schema->getType('ChildType');

        $stringType = $schema->getType('String');
        $intType = $schema->getType('Int');

        $this->assertSame($intType, $childType->getField('attribute_1')->getType());
        $this->assertSame($stringType, $childType->getField('attribute_2')->getType());
    }

    public function testChildAttributesShouldNotBeAddedParent()
    {
        $this->expectException(\GraphQL\Error\InvariantViolation::class);

        $schema = $this->buildSchema(/* @lang GraphQL */ '
            type ParentType {
                attribute_1: String
                another_attribute: String
            }
            type ChildType @inherits(from: ParentType) {
                new_attribute: String
            }

            type Query {
                parentypeQuery: ParentType
                childtypeQuery: ChildType
            }');

        $parentType = $schema->getType('ParentType');
        $parentType->getField('new_attribute');
    }

    public function testCanNotInheritsOtherTypes()
    {
        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->buildSchema(/*  @lang GraphQL */ '
            input ParentType {
                attribute_1: String
            }
            type ChildType @inherits(from: ParentType) {
                attribute_2: String
            }
            type Query {
                childtypeQuery: ChildType @mock
            }
        ');
    }

    public function testExceptionWhenTypeDoesntExist()
    {
        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->buildSchema(/*  @lang GraphQL */ '
            type ChildType @inherits(from: UndefinedType) {
                attribute_1: String
            }
            type Query {
                childTypeQuery: ChildType @mock
            }
        ');
    }
}
