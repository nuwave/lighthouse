<?php

namespace Tests\Unit\Schema\Factories;

use Closure;
use Tests\TestCase;
use ReflectionProperty;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DirectiveFactoryTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    public function getEnvironmentSetUp($app): void
    {
        $this->directiveFactory = $app->make(DirectiveFactory::class);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * @test
     */
    public function itRegistersLighthouseDirectives(): void
    {
        $this->assertInstanceOf(
            FieldDirective::class,
            $this->directiveFactory->create((new FieldDirective)->name())
        );
    }

    /**
     * @test
     */
    public function itHydratesBaseDirectives(): void
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String
        ');

        $fieldDirective = $this->directiveFactory->create('field', $fieldDefinition);

        $definitionNode = new ReflectionProperty($fieldDirective, 'definitionNode');
        $definitionNode->setAccessible(true);

        $this->assertSame(
            $fieldDefinition,
            $definitionNode->getValue($fieldDirective)
        );
    }

    /**
     * @test
     */
    public function itSkipsHydrationForNonBaseDirectives(): void
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String
        ');

        $directive = new class implements FieldMiddleware {
            public function name(): string
            {
                return 'foo';
            }

            public function handleField(FieldValue $fieldValue, Closure $next): void
            {
                //
            }
        };

        $this->directiveFactory->setResolved('foo', get_class($directive));
        $directive = $this->directiveFactory->create('foo', $fieldDefinition);

        $this->assertObjectNotHasAttribute('definitionNode', $directive);
    }

    /**
     * @test
     */
    public function itThrowsIfDirectiveNameCanNotBeResolved(): void
    {
        $this->expectException(DirectiveException::class);

        $this->directiveFactory->create('bar');
    }

    /**
     * @test
     */
    public function itCanCreateSingleDirective(): void
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: [Foo!]! @hasMany
        ');

        $resolver = $this->directiveFactory->createSingleDirectiveOfType($fieldDefinition, FieldResolver::class);
        $this->assertInstanceOf(FieldResolver::class, $resolver);
    }

    /**
     * @test
     */
    public function itThrowsExceptionWhenMultipleFieldResolverDirectives(): void
    {
        $this->expectException(DirectiveException::class);

        $fieldDefinition = PartialParser::fieldDefinition('
            bar: [Bar!]! @hasMany @belongsTo
        ');

        $this->directiveFactory->createSingleDirectiveOfType($fieldDefinition, FieldResolver::class);
    }

    /**
     * @test
     */
    public function itCanCreateMultipleDirectives(): void
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            bar: String @can(if: ["viewBar"]) @event
        ');

        $middleware = $this->directiveFactory->createAssociatedDirectivesOfType($fieldDefinition, FieldMiddleware::class);
        $this->assertCount(2, $middleware);
    }
}
