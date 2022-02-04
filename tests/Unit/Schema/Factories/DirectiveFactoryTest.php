<?php

namespace Tests\Unit\Schema\Factories;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\FallbackTypeNodeConverter;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

class DirectiveFactoryTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    public function setUp(): void
    {
        parent::setUp();

        $typeRegistry = app(TypeRegistry::class);
        $this->directiveFactory = new DirectiveFactory(
            new FallbackTypeNodeConverter($typeRegistry)
        );
    }

    public function testConvertDirectiveFromNodeToExecutable(): void
    {
        $node = Parser::directiveDefinition(/** @lang GraphQL */ '
        "foo description"
        directive @foo(
            """
            baz
            description
            """
            baz: Int
        ) repeatable on OBJECT
        ');
        $executable = $this->directiveFactory->handle($node);

        $this->assertSame('foo description', $executable->description);
        $this->assertTrue($executable->isRepeatable);
        $this->assertSame([DirectiveLocation::OBJECT], $executable->locations);
        $this->assertSame($node, $executable->astNode);
        $this->assertSame('foo', $executable->name);

        $this->assertCount(1, $executable->args);
        $arg = $executable->args[0];
        $this->assertSame('baz', $arg->name);
        $this->assertSame("baz\ndescription", $arg->description);
        $this->assertSame(Type::INT, $arg->getType()->name);
    }
}
