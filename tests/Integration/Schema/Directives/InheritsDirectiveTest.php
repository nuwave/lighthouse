<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

class InheritsDirectiveTest extends TestCase
{
    public function testCanInheritsTypes()
    {
        $this->mockResolver(function ($root, array $args): array {
            return [
                'attribute_1' => 'Attribute 1',
                'attribute_2' => 'Attribute 2',
            ];
        });

        $this->schema = /* @lang GraphQL */ '
            type ParentType {
                attribute_1: String
            }
            type ChildType @inherits(parent: ParentType) {
                attribute_2: String
            }

            type Query {
                childtypeQuery: ChildType @mock
            }';

        $response = $this->graphQL(
            'query TestChildType{
               childtypeQuery {
                   attribute_1
                   attribute_2
               }
            }'
        );
        $response->assertExactJson([
            'data' => [
                'childtypeQuery' => [
                    'attribute_1' => 'Attribute 1',
                    'attribute_2' => 'Attribute 2',
                ],
            ],
        ]);
    }

    public function testChildOverridesFields()
    {
        $this->mockResolver(function ($root, array $args): array {
            return [
                'attribute_1' => '100',
                'attribute_2' => 100,
            ];
        });

        $this->schema =
            /* @lang GraphQL */
            '
            type ParentType {
                attribute_1: String
                attribute_2: Int
            }
            type ChildType @inherits(parent: ParentType) {
                attribute_1: Int
                attribute_2: String
            }

            type Query {
                childtypeQuery: ChildType @mock
            }';

        $response = $this->graphQL(
            'query TestChildType{
               childtypeQuery {
                   attribute_1
                   attribute_2
               }
            }'
        );

        $response->assertExactJson([
            'data' => [
                'childtypeQuery' => [
                    'attribute_1' => 100,
                    'attribute_2' => '100',
                ],
            ],
        ]);
    }

    public function testChildAttributesShouldNotBeAddedParent()
    {
        $this->rethrowGraphQLErrors();

        $this->expectException(\GraphQL\Error\Error::class);

        $this->mockResolverExpects($this->never());
        $this->schema = /* @lang GraphQL */ '
            type ParentType {
                attribute_1: String
                another_attribute: String
            }
            type ChildType @inherits(parent: ParentType) {
                new_attribute: String
            }

            type Query {
                parentypeQuery: ParentType @mock
                childtypeQuery: ChildType @mock
            }';

        $this->graphQL(
            'query TestChildType{
               childtypeQuery {
                   attribute_1
                   new_attribute
               }
                parentypeQuery {
                   attribute_1
                   new_attribute
               }
            }'
        );
    }

    public function testCanNotInheritsOtherTypes()
    {
        $this->rethrowGraphQLErrors();

        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->mockResolverExpects($this->never());
        $this->schema = /*  @lang GraphQL */ '
            input ParentType {
                attribute_1: String
            }
            type ChildType @inherits(parent: ParentType) {
                attribute_2: String
            }
            type Query {
                childtypeQuery: ChildType @mock
            }
        ';

        $this->graphQL(
            'query testquery {
                childtypeQuery {
                    attribute_2
                    attribute_1
                }
            }'
        );
    }
}
