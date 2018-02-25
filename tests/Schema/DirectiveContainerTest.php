<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Tests\TestCase;

class DirectiveContainerTest extends TestCase
{
    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(ScalarDirective::class, directives()->handler('scalar'));
    }

    /**
     * @test
     */
    public function itGetsLighthouseHandlerForScalar()
    {
        $schema = 'scalar Email @scalar(class: "EmailScalar")';
        $document = Parser::parse($schema);
        $handler = directives()->forNode($document->definitions[0]);
        $scalar = $handler->resolve($document->definitions[0]);

        $this->assertInstanceOf(ScalarDirective::class, $handler);
        $this->assertInstanceOf(ScalarType::class, $scalar);
    }

    /**
     * @test
     */
    public function itThrowsErrorIfMultipleDirectivesAssignedToNode()
    {
        $this->expectException(DirectiveException::class);

        $schema = 'scalar DateTime @scalar @foo';
        $document = Parser::parse($schema);
        $handler = directives()->forNode($document->definitions[0]);
    }
}
