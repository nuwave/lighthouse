<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Category;
use Tests\Utils\Models\Color;

class UsesTestSchemaTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCanSwitchSchemaOnSucessiveRequests(): void
    {
        factory(Color::class, 3)->create();
        factory(Category::class, 4)->create();

        // It passes
        $this->schema = '          
            type Color {                
                id: ID!
                name: String!
            }

            type Query {
                colors: [Color] @all
            }
            ';

        $this->graphQL('
            {
                colors{
                    id
                    name
                }
            }
            ')
            ->assertJsonStructure([
                'data' => ['colors']
            ])
            ->assertJsonCount(3, 'data.colors');


        $this->schema = '          
            type Color {                
                id: ID!
                name: String!
            }

            type Category {                
                id: ID!
                name: String!
            }

            type Query {
                colors: [Color] @all
                categories: [Category] @all
            }
            ';

        // It fails, because schema cannot be overridden 
        $this->graphQL('
            {
                categories{
                    id
                    name
                }
            }
            ')
            ->assertJsonStructure([
                'data' => ['categories']
            ])
            ->assertJsonCount(4, 'data.categories');
    }
}
