<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use Tests\TestCase;

class GenerateDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itGeneratesDefaultQueries()
    {
        $schema = '
        type User @generate(
            model: "User"
            crud: true
            ){
            id: ID!
            name: String
        }
        ';
    
        $query = schema()->build($schema)->getQueryType();
        $queryFields = $query->getFields();
        
        $this->assertArrayHasKey('users', $queryFields);
        $multiQuery = $queryFields['users'];
        $this->assertInstanceOf(FieldDefinition::class, $multiQuery);
        
        $this->assertArrayHasKey('user', $queryFields);
        $singleQuery = $queryFields['user'];
        $this->assertInstanceOf(FieldDefinition::class, $singleQuery);
        $this->assertInstanceOf(FieldArgument::class, $singleQuery->args[0]);
    }
}
