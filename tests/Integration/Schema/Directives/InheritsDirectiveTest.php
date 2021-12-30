<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

class InheritsDirectiveTest extends TestCase
{
    public function testSimpleInheritance(): void
    {
        $schema = $this->buildSchema(/**@lang GraphQL */ '
            type ParentType {
                attribute_1: String
            }

            type ChildType @inherits(from: ParentType) {
                attribute_2: String
            }
        ' . self::PLACEHOLDER_QUERY);

        $childType = $schema->getType('ChildType');

        $this->assertNotNull($childType->getField('attribute_1'));
    }

    public function testInheritTransitive(): void
    {
        $schema = $this->buildSchema(/**@lang GraphQL */ '
            type GrandParentType {
                attribute_1: String
            }

            type ParentType @inherits(from: GrandParentType) {
                attribute_2: String
            }

            type ChildType @inherits(from: ParentType) {
                attribute_3: String
            }
        ' . self::PLACEHOLDER_QUERY);

        $childType = $schema->getType('ChildType');

        $this->assertNotNull($childType->getField('attribute_1'));
        $this->assertNotNull($childType->getField('attribute_2'));
        $this->assertNotNull($childType->getField('attribute_3'));
    }

    public function testSchemaOrderIsInsignificant(): void
    {
        $schema = $this->buildSchema(/**@lang GraphQL */ '
            type ChildType @inherits(from: ParentType) {
                attribute_3: String
            }

            type ParentType @inherits(from: GrandParentType) {
                attribute_2: String
            }

            type GrandParentType {
                attribute_1: String
            }
        ' . self::PLACEHOLDER_QUERY);

        $childType = $schema->getType('ChildType');

        $this->assertNotNull($childType->getField('attribute_1'));
        $this->assertNotNull($childType->getField('attribute_2'));
        $this->assertNotNull($childType->getField('attribute_3'));
    }

    public function testChildOverridesFields(): void
    {
        $schema = $this->buildSchema(/* @lang GraphQL */ '
            type ParentType {
                attribute_1: String
                attribute_2: Int
            }

            type ChildType @inherits(from: ParentType) {
                attribute_1: Int
                attribute_2: String
            }
        ' . self::PLACEHOLDER_QUERY);

        $childType = $schema->getType('ChildType');

        $stringType = $schema->getType('String');
        $intType = $schema->getType('Int');

        $this->assertSame($intType, $childType->getField('attribute_1')->getType());
        $this->assertSame($stringType, $childType->getField('attribute_2')->getType());
    }

    public function testChildAttributesShouldNotBeAddedParent(): void
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
        ' . self::PLACEHOLDER_QUERY);

        $parentType = $schema->getType('ParentType');
        $parentType->getField('new_attribute');
    }

    public function testInheritedTypeHasToMatch(): void
    {
        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->buildSchema(/*  @lang GraphQL */ '
            input ParentType {
                attribute_1: String
            }
            
            type ChildType @inherits(from: ParentType) {
                attribute_2: String
            }
        ' . self::PLACEHOLDER_QUERY);
    }

    public function testInheritUndefinedType(): void
    {
        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->buildSchema(/*  @lang GraphQL */ '
            type ChildType @inherits(from: UndefinedType) {
                attribute_1: String
            }
        ' . self::PLACEHOLDER_QUERY);
    }
}
