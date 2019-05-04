<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;

class PaginateDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanAliasRelayToConnection(): void
    {
        $connection = $this->getConnectionQueryField('connection');
        $relay = $this->getConnectionQueryField('relay');

        $this->assertSame($connection, $relay);
    }

    protected function getConnectionQueryField(string $type): FieldDefinition
    {
        return $this
            ->buildSchema("
            type User {
                name: String
            }
            
            type Query {
                users: [User!]! @paginate(type: \"$type\")
            }
            ")
            ->getQueryType()
            ->getField('users');
    }

    /**
     * @test
     */
    public function itOnlyRegistersOneTypeForMultiplePaginators(): void
    {
        $schema = $this->buildSchema('
        type User {
            name: String
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
            users3: [User!]! @paginate(type: "connection")
        }
        
        type Query {
            users: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
            users3: [User!]! @paginate(type: "connection")
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey(
            'UserPaginator',
            $typeMap
        );

        $this->assertArrayHasKey(
            'UserConnection',
            $typeMap
        );
    }

    /**
     * @test
     */
    public function itRegistersPaginatorFromTypeExtensionField(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate
        }
        ');
        $typeMap = $schema->getTypeMap();

        $this->assertArrayHasKey(
            'UserPaginator',
            $typeMap
        );

        // See https://github.com/nuwave/lighthouse/issues/387
        $this->assertArrayNotHasKey(
            'UserPaginatorPaginator',
            $typeMap
        );
    }

    /**
     * @test
     */
    public function itHasMaxCountInGeneratedCountDescription(): void
    {
        config(['lighthouse.paginate_max_count' => 5]);

        $queryType = $this
            ->buildSchema('
            type Query {
                defaultPaginated: [User!]! @paginate
                defaultRelay: [User!]! @paginate(type: "relay")
                customPaginated:  [User!]! @paginate(maxCount: 10)
                customRelay:  [User!]! @paginate(maxCount: 10, type: "relay")
            }

            type User {
                id: ID!
            }
            ')
            ->getQueryType();

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $queryType->getField('defaultPaginated')->getArg(config('lighthouse.pagination_amount_argument'))->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 5.',
            $queryType->getField('defaultRelay')->getArg('first')->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $queryType->getField('customPaginated')->getArg(config('lighthouse.pagination_amount_argument'))->description
        );

        $this->assertSame(
            'Limits number of fetched elements. Maximum allowed value: 10.',
            $queryType->getField('customRelay')->getArg('first')->description
        );
    }

    public function itCanChangePaginationAmountArgument(): void
    {
        config(['lighthouse.pagination_amount_argument' => 'first']);

        $queryType = $this
            ->buildSchema('
            type Query {
                defaultPaginated: [User!]! @paginate
            }

            type User {
                id: ID!
            }
            ')
            ->getQueryType();

        $this->assertInstanceOf(
            FieldArgument::class,
            $queryType->getField('defaultPaginated')->getArg('first')
        );
    }
}
