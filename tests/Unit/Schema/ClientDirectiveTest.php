<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use GraphQL\Type\Definition\Directive;

class ClientDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itReturnsDefaultDirectivesInIntrospection(): void
    {
        $this->schema = $this->placeholderQuery();

        $this->assertNotNull(
            $this->introspectDirective(Directive::SKIP_NAME)
        );
        $this->assertNotNull(
            $this->introspectDirective(Directive::INCLUDE_NAME)
        );
    }

    /**
     * @test
     */
    public function itCanDefineACustomClientDirective(): void
    {
        $this->schema = '
        "foo"
        directive @bar(
            "foobar"
            baz: String = "barbaz"
        ) on FIELD
        '.$this->placeholderQuery();

        $bar = $this->introspectDirective('bar');

        $this->assertSame(
            [
                'name' => 'bar',
                'description' => 'foo',
                'locations' => [
                    'FIELD',
                ],
                'args' => [
                    [
                        'name' => 'baz',
                        'description' => 'foobar',
                        'type' => [
                            'kind' => 'SCALAR',
                            'name' => 'String',
                            'ofType' => null,
                        ],
                        'defaultValue' => '"barbaz"',
                    ],
                ],
            ],
            $bar
        );
    }
}
