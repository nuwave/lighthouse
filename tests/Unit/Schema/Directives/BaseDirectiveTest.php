<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
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
        type User @modelClass(class: "Team") {
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

    protected function constructFieldDirective(string $definitionNode): BaseDirective
    {
        return $this->constructTestDirective(
            PartialParser::fieldDefinition($definitionNode)
        );
    }

    /**
     * Get a testable instance of the BaseDirective that allows calling protected methods.
     *
     * @param  \GraphQL\Language\AST\TypeSystemDefinitionNode  $definitionNode
     * @return \Nuwave\Lighthouse\Schema\Directives\BaseDirective
     */
    protected function constructTestDirective($definitionNode): BaseDirective
    {
        $directive = new class extends BaseDirective {
            /**
             * Allow to call protected methods from the test.
             *
             * @param  string  $method
             * @param  mixed[]  $args
             * @return mixed
             */
            public function __call(string $method, array $args)
            {
                return call_user_func_array([$this, $method], $args);
            }
        };

        $directive->hydrate(
            $definitionNode->directives[0],
            $definitionNode
        );

        return $directive;
    }
}
