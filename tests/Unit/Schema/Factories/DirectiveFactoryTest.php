<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Factories;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\AST\FallbackTypeNodeConverter;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\TestCase;

final class DirectiveFactoryTest extends TestCase
{
    protected DirectiveFactory $directiveFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $typeRegistry = $this->app->make(TypeRegistry::class);
        $this->directiveFactory = new DirectiveFactory(
            new FallbackTypeNodeConverter($typeRegistry),
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
        $this->assertSame(Type::int(), $arg->getType());
    }
}
