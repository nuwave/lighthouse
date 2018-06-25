<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\TestCase;

class RulesDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanApplyRulesToInputTypes()
    {
        $resolver = addslashes(self::class);
        $schema = "
        input FooInput {
            bar: String @rules(apply: [\"required\"])
            baz: String @rules(apply: [\"required\"])
        }

        type Query {}

        type Mutation {
            foo(input: FooInput!): String 
                @field(resolver: \"{$resolver}@resolve\")
        }";

        $result = $this->executeAndFormat($schema, 'mutation { foo(input: { bar: "" }) }');
        $this->assertNull(array_get($result, 'data.foo'));
        $this->assertCount(2, array_get($result, 'errors.0.validation'));
    }

    /**
     * @return string
     */
    public function resolve()
    {
        return 'foo';
    }
}
