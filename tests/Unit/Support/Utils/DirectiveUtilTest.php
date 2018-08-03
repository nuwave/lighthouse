<?php

namespace Tests\Unit\Support\Utils;

use Tests\TestCase;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Support\Utils\DirectiveUtil;

class DirectiveUtilTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGetFieldDirectiveByName()
    {
        $field = PartialParser::field('dummy: String @foo @bar');

        $this->assertInstanceOf(
            DirectiveNode::class,
            DirectiveUtil::fieldDirective($field, 'foo')
        );

        $this->assertInstanceOf(
            DirectiveNode::class,
            DirectiveUtil::fieldDirective($field, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractStringArguments()
    {
        $directive = PartialParser::directive('@foo(bar: "baz")');

        $this->assertEquals(
            'baz',
            DirectiveUtil::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractBooleanArguments()
    {
        $directive = PartialParser::directive('@foo(bar: true)');

        $this->assertEquals(
            true,
            DirectiveUtil::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractArrayArguments()
    {
        $directive = PartialParser::directive('@foo(bar: ["one", "two"])');

        $this->assertEquals(
            ['one', 'two'],
            DirectiveUtil::directiveArgValue($directive, 'bar')
        );
    }

    /**
     * @test
     */
    public function itCanExtractObjectArguments()
    {
        $directive = PartialParser::directive('@foo(bar: { baz: "foobar" })');

        $this->assertEquals(
            ['baz' => 'foobar'],
            DirectiveUtil::directiveArgValue($directive, 'bar')
        );
    }
}
