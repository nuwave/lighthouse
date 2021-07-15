<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ExtendsDirectiveTest extends TestCase
{
    public function testCanExtendTypes()
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
            type ChildType @extends(parent: ParentType) {
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

    public function testChildOverwritesParentAttributes()
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
            type ChildType @extends(parent: ParentType) {
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

        $response->assertJson(
            fn (AssertableJson $json) => $json->has(
                'data.childtypeQuery',
                fn ($json) => $json->whereAllType([
                    'attribute_1' => 'integer',
                    'attribute_2' => 'string',
                ])
                    ->etc()
            )
        );
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
            type ChildType @extends(parent: ParentType) {
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

    public function testCanNotExtendOtherTypes()
    {
        $this->rethrowGraphQLErrors();

        $this->expectException(\Nuwave\Lighthouse\Exceptions\DefinitionException::class);

        $this->mockResolverExpects($this->never());
        $this->schema = /*  @lang GraphQL */ '
            input ParentType {
                attribute_1: String
            }
            type ChildType @extends(parent: ParentType) {
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
