<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;

class CacheManagerTest extends TestCase
{
    /**
     * @test
     * @group failing
     */
    public function itCanCacheSchema()
    {
        $schema = '
        type Task {
            foo: String!
        }
        type User {
            foo: String!
            tasks: [Task!]! @hasMany
        }
        type Query {
            foo(bar: String! baz: String!): String
        }
        type Mutation {
            foo(bar: String! baz: String!): String
        }
        ';

        schema()->register($schema);

        $cache = schema()->serialize();
        $this->assertTrue(is_string($cache));

        $types = schema()->unserialize($cache);

        $mutation = $types->firstWhere('name', 'Mutation');
        $foo = $mutation->config['fields']['foo']['resolve'];
        $this->assertEquals('bar baz', $foo(null, ['bar' => 'bar', 'baz' => 'baz']));

        $query = $types->firstWhere('name', 'Mutation');
        $foo = $query->config['fields']['foo']['resolve'];
        $this->assertEquals('foo bar', $foo(null, ['bar' => 'foo', 'baz' => 'bar']));
    }
}
