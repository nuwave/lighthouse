<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
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

        extend type Query @group {
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
    public function itAddsDescriptionToCountArgument(): void
    {
        $schema = $this->buildSchema('
        type User {
            name: String
        }
        
        type Query {
            users1: [User!]! @paginate(maxCount: 10)
            users2: [User!]! @paginate(maxCount: 10, type: "relay")
        }
        ');
        $typeMap = $schema->getTypeMap();

        // default pagination
        $argsConnect = $schema->getQueryType('users1')->getField('users1')->args;
        $countArgConnect = Arr::first($argsConnect, function (FieldArgument $arg) {
            return $arg->name === 'count';
        });

        $this->assertSame('Limits number of fetched elements. Maximum: 10', $countArgConnect->description);

        // relay pagination
        $argsRelay = $schema->getQueryType('users2')->getField('users2')->args;
        $countArgRelay = Arr::first($argsRelay, function (FieldArgument $arg) {
            return $arg->name === 'first';
        });

        $this->assertSame('Limits number of fetched elements. Maximum: 10', $countArgRelay->description);
    }
}
