<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;

class ComplexityDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetDefaultComplexityOnField()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
        type User {
            posts: [Post!]! @complexity @hasMany
        }
        
        type Post {
            title: String
        }
        ');
        $type = $schema->getType('User');
        $fields = $type->config['fields']();
        $complexity = $fields['posts']['complexity'];

        $this->assertEquals(100, $complexity(10, ['first' => 10]));
        $this->assertEquals(100, $complexity(10, ['count' => 10]));
    }

    /**
     * @test
     */
    public function itCanSetCustomComplexityResolver()
    {
        $resolver = addslashes(self::class);

        $schema = $this->buildSchemaWithDefaultQuery('
        type User {
            posts: [Post!]!
                @complexity(resolver: "'.$resolver.'@complexity")
                @hasMany
        }
        
        type Post {
            title: String
        }
        ');
        $type = $schema->getType('User');
        $fields = $type->config['fields']();
        $complexity = $fields['posts']['complexity'];

        $this->assertEquals(100, $complexity(10, ['foo' => 10]));
    }

    public function complexity($children, array $args)
    {
        return $children * array_get($args, 'foo', 0);
    }
}
