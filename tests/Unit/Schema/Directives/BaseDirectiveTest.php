<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Tests\TestCase;
use Tests\Utils\Models\Category;
use Tests\Utils\Models\Closure;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use Tests\Utils\ModelsSecondary\Category as CategorySecondary;
use Tests\Utils\ModelsSecondary\OnlyHere;

/**
 * This class does test the internal behaviour of the BaseDirective class.
 *
 * While typically considered an anti-pattern, the BaseDirective is meant
 * to be extended by other directives and offers basic utilities that
 * are commonly used in directives. As users may also extend it to create
 * custom directives, its behaviour should be stable and well-defined.
 */
class BaseDirectiveTest extends TestCase
{
    public function testGetsModelClassFromDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User @model(class: "Team") {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: User @dummy');

        $this->assertSame(
            Team::class,
            $directive->getModelClass()
        );
    }

    public function testGetsNameFromDirective(): void
    {
        $directive = $this->constructFieldDirective('foo: ID @dummy');

        $this->assertSame(
            'dummy',
            $directive->name()
        );
    }

    public function testDefaultsToFieldTypeForTheModelClass(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: User @dummy');

        $this->assertSame(
            User::class,
            $directive->getModelClass()
        );
    }

    public function testThrowsIfTheClassIsNotInTheSchema(): void
    {
        $directive = $this->constructFieldDirective('foo: UnknownType @dummy');

        $this->expectException(DefinitionException::class);
        $directive->getModelClass();
    }

    public function testThrowsIfTheClassIsNotAModel(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Exception {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: Exception @dummy');

        $this->expectException(DefinitionException::class);
        $directive->getModelClass();
    }

    public function testResolvesAModelThatIsNamedLikeABaseClass(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Closure {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: Closure @dummy');

        $this->assertSame(
            Closure::class,
            $directive->getModelClass()
        );
    }

    public function testPrefersThePrimaryModelNamespace(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Category {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: Category @dummy');

        $this->assertSame(
            Category::class,
            $directive->getModelClass()
        );
    }

    public function testAllowsOverwritingTheDefaultModel(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type OnlyHere {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: OnlyHere @dummy(model: "Tests\\\Utils\\\ModelsSecondary\\\Category")');

        $this->assertSame(
            CategorySecondary::class,
            $directive->getModelClass()
        );
    }

    public function testResolvesFromTheSecondaryModelNamespace(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type OnlyHere {
            id: ID
        }
        ';

        $directive = $this->constructFieldDirective('foo: OnlyHere @dummy');

        $this->assertSame(
            OnlyHere::class,
            $directive->getModelClass()
        );
    }

    public function testGetsArgumentFromDirective(): void
    {
        $directive = $this->constructFieldDirective('foo: ID @dummy(argName:"argValue", argName2:"argValue2")');

        $this->assertSame(
            'argValue',
            // @phpstan-ignore-next-line protected method is called via wrapper below
            $directive->directiveArgValue('argName')
        );

        $this->assertSame(
            'argValue2',
            // @phpstan-ignore-next-line protected method is called via wrapper below
            $directive->directiveArgValue('argName2')
        );
    }

    public function testTwoArgumentsWithSameName(): void
    {
        $directive = $this->constructFieldDirective('foo: ID @dummy(argName:"argValue", argName:"argValue2")');

        $this->expectException(DefinitionException::class);
        // @phpstan-ignore-next-line protected method is called via wrapper below
        $directive->directiveArgValue('argName');
    }

    protected function constructFieldDirective(string $definition): BaseDirective
    {
        $fieldDefinition = Parser::fieldDefinition($definition);

        $directive = new class extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @baseTest on FIELD_DEFINITION';
            }

            /**
             * Allow to call protected methods from the test.
             *
             * @param  array<mixed>  $args
             * @return mixed Whatever the method returns.
             */
            public function __call(string $method, array $args)
            {
                return $this->{$method}(...$args);
            }
        };

        $directive->hydrate(
            $fieldDefinition->directives[0],
            $fieldDefinition
        );

        return $directive;
    }
}
