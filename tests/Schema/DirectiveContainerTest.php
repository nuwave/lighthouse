<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use GraphQL\Language\Parser;

use Nuwave\Lighthouse\Tests\TestCase;

use Nuwave\Lighthouse\Schema\Directives\ScalarDirective;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
        $schema = 'scalar DateTime @scalar';
        $document = Parser::parse($schema);
        $handler = directives()->forNode($document->definitions[0]);

        $this->assertInstanceOf(ScalarDirective::class, $handler);
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
