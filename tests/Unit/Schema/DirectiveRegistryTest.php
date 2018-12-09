<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldDirective;

class DirectiveRegistryTest extends TestCase
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;

    protected function setUp()
    {
        parent::setUp();

        $this->directiveRegistry = app(DirectiveRegistry::class);
    }

    /**
     * @test
     */
    public function itRegistersLighthouseDirectives()
    {
        $this->assertInstanceOf(
            FieldDirective::class,
            $this->directiveRegistry->get((new FieldDirective)->name())
        );
    }

    /**
     * @test
     */
    public function itRegistersDirectiveFromProgrammaticallyGivenLocation()
    {
        $this->expectException(DirectiveException::class);
        $this->directiveRegistry->get((new FooDirective)->name());

        $this->directiveRegistry->load(
            __DIR__ . '../../Utils/Directives/Programmatically',
            'Tests\Utils\Directives\Programmatically',
            __DIR__ . '/../../'
        );

        $this->assertInstanceOf(
            FooDirective::class,
            $this->directiveRegistry->get((new FooDirective)->name())
        );
    }

    /**
     * @test
     */
    public function itThrowsErrorIfMultipleDirectivesAssignedToNode()
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchemaWithPlaceholderQuery('
            scalar DateTime @scalar @foo
        ');
    }

    /**
     * @test
     */
    public function itCanGetFieldResolverDirective()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: [Foo!]! @hasMany
        ');

        $resolver = $this->directiveRegistry->fieldResolver($fieldDefinition);
        $this->assertInstanceOf(FieldResolver::class, $resolver);
    }

    /**
     * @test
     */
    public function itThrowsExceptionWhenMultipleFieldResolverDirectives()
    {
        $this->expectException(DirectiveException::class);

        $fieldDefinition = PartialParser::fieldDefinition('
            bar: [Bar!]! @hasMany @belongsTo
        ');

        $this->directiveRegistry->fieldResolver($fieldDefinition);
    }

    /**
     * @test
     */
    public function itCanGetCollectionOfFieldMiddleware()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            bar: String @can(if: ["viewBar"]) @event
        ');

        $middleware = $this->directiveRegistry->fieldMiddleware($fieldDefinition);
        $this->assertCount(2, $middleware);
    }

    /**
     * @test
     */
    public function itCanRegisterDirectivesDirectly()
    {
        $fooDirective = new class() implements Directive {
            public function name()
            {
                return 'foo';
            }
        };

        $this->directiveRegistry->register($fooDirective);

        $this->assertEquals($fooDirective, $this->directiveRegistry->get('foo'));
        $this->assertNotSame($fooDirective, $this->directiveRegistry->get('foo'));
    }

    /**
     * @test
     */
    public function itHydratesBaseDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        $this->directiveRegistry->register(
            new class() extends BaseDirective implements FieldMiddleware {
                public function name()
                {
                    return 'foo';
                }

                public function getFieldDefinition()
                {
                    return $this->definitionNode;
                }

                public function handleField(FieldValue $value, \Closure $next)
                {
                }
            }
        );

        $fooDirective = $this->directiveRegistry->fieldMiddleware($fieldDefinition)->first();
        $this->assertSame($fieldDefinition, $fooDirective->getFieldDefinition());
    }

    /**
     * @deprecated this test is for compatibility reasons and can likely be removed in v3
     * @test
     */
    public function itSkipsHydrationForNonBaseDirectives()
    {
        $fieldDefinition = PartialParser::fieldDefinition('
            foo: String @foo
        ');

        $originalDefinition = new class() implements FieldMiddleware {
            public function name()
            {
                return 'foo';
            }

            public function handleField(FieldValue $value, \Closure $next)
            {
            }
        };
        $this->directiveRegistry->register($originalDefinition);

        $fromRegistry = $this->directiveRegistry->fieldMiddleware($fieldDefinition)->first();
        $this->assertEquals($originalDefinition, $fromRegistry);
        $this->assertNotSame($originalDefinition, $fromRegistry);
    }
}
