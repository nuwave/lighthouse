<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Directives\FieldDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Utils;
use Tests\TestCase;

final class DirectiveLocatorTest extends TestCase
{
    private DirectiveLocator $directiveLocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directiveLocator = $this->app->make(DirectiveLocator::class);
    }

    public function testRegistersLighthouseDirectives(): void
    {
        $this->assertInstanceOf(
            FieldDirective::class,
            $this->directiveLocator->create('field'),
        );
    }

    public function testHydratesBaseDirectives(): void
    {
        $fieldDefinition = Parser::fieldDefinition(/** @lang GraphQL */ '
            foo: String @field
        ');

        $fieldDirective = $this
            ->directiveLocator
            ->associated($fieldDefinition)
            ->first();
        assert($fieldDirective instanceof FieldDirective);

        $this->assertSame(
            $fieldDefinition,
            Utils::accessProtected($fieldDirective, 'definitionNode'),
        );
    }

    public function testSkipsHydrationForNonBaseDirectives(): void
    {
        $fieldDefinition = Parser::fieldDefinition(/** @lang GraphQL */ '
            foo: String @foo
        ');

        $directive = new class() implements FieldMiddleware {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'foo';
            }

            public function handleField(FieldValue $fieldValue): void {}
        };

        $this->directiveLocator->setResolved('foo', $directive::class);

        $directive = $this
            ->directiveLocator
            ->associated($fieldDefinition)
            ->first();

        self::assertNotInstanceOf(BaseDirective::class, $directive);
    }

    public function testThrowsIfDirectiveNameCanNotBeResolved(): void
    {
        $this->expectException(DirectiveException::class);

        $this->directiveLocator->create('bar');
    }

    public function testCreateSingleDirective(): void
    {
        $fieldDefinition = Parser::fieldDefinition(/** @lang GraphQL */ '
            foo: [Foo!]! @hasMany
        ');

        $resolver = $this->directiveLocator->exclusiveOfType($fieldDefinition, FieldResolver::class);
        $this->assertInstanceOf(FieldResolver::class, $resolver);
    }

    public function testThrowsExceptionWhenMultipleFieldResolverDirectives(): void
    {
        $this->expectException(DirectiveException::class);
        $this->expectExceptionMessage("Node bar can only have one directive of type Nuwave\Lighthouse\Support\Contracts\FieldResolver but found [@hasMany, @belongsTo].");

        $fieldDefinition = Parser::fieldDefinition(/** @lang GraphQL */ '
            bar: [Bar!]! @hasMany @belongsTo
        ');

        $this->directiveLocator->exclusiveOfType($fieldDefinition, FieldResolver::class);
    }

    public function testCreateMultipleDirectives(): void
    {
        $fieldDefinition = Parser::fieldDefinition(/** @lang GraphQL */ '
            bar: String @can(if: ["viewBar"]) @event
        ');

        $middleware = $this->directiveLocator->associatedOfType($fieldDefinition, FieldMiddleware::class);
        $this->assertCount(2, $middleware);
    }
}
