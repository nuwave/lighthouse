<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Closure;
use Tests\Utils\Models\Category;
use Tests\Utils\ModelsSecondary\OnlyHere;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Tests\Utils\ModelsSecondary\Category as CategorySecondary;

/**
 * This class does test the internal behaviour of the BaseDirective class.
 *
 * While typically considered an anti-pattern, the BaseDirective is meant
 * to be extended by other directives and offers basic utilities that
 * are commonly used in directives. As users may also extend it to create
 * custom directives, its behaviour should be pretty stable and well defined.
 */
class BaseDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itDefaultsToFieldTypeForTheModelClass(): void
    {
        $directive = $this->constructFieldDirective('foo: User @dummy');

        $this->assertSame(
            User::class,
            $directive->getModelClass()
        );
    }

    /**
     * @test
     */
    public function itThrowsIfTheClassIsNotAModel(): void
    {
        $directive = $this->constructFieldDirective('foo: Exception @dummy');

        $this->expectException(DirectiveException::class);
        $directive->getModelClass();
    }

    /**
     * @test
     */
    public function itResolvesAModelThatIsNamedLikeABaseClass(): void
    {
        $directive = $this->constructFieldDirective('foo: Closure @dummy');

        $this->assertSame(
            Closure::class,
            $directive->getModelClass()
        );
    }

    /**
     * @test
     */
    public function itPrefersThePrimaryModelNamespace(): void
    {
        $directive = $this->constructFieldDirective('foo: Category @dummy');

        $this->assertSame(
            Category::class,
            $directive->getModelClass()
        );
    }

    /**
     * @test
     */
    public function itAllowsOverwritingTheDefaultModel(): void
    {
        $directive = $this->constructFieldDirective('foo: OnlyHere @dummy(model: "Tests\\\Utils\\\ModelsSecondary\\\Category")');

        $this->assertSame(
            CategorySecondary::class,
            $directive->getModelClass()
        );
    }

    /**
     * @test
     */
    public function itResolvesFromTheSecondaryModelNamespace(): void
    {
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
     * Get a testable instance of the BaseDirective.
     *
     * Calls to non-public methods are piped through by the
     *
     * @param  \GraphQL\Language\AST\TypeSystemDefinitionNode  $definitionNode
     * @return \Nuwave\Lighthouse\Schema\Directives\BaseDirective
     */
    protected function constructTestDirective($definitionNode): BaseDirective
    {
        $directive = new class() extends BaseDirective {
            /**
             * Name of the directive.
             *
             * @return string
             */
            public function name(): string
            {
                return 'dummy';
            }

            /**
             * Allow to call protected methods from the test.
             *
             * @param  string  $method
             * @param  mixed[] $args
             * @return mixed
             */
            public function __call(string $method, $args)
            {
                return call_user_func_array([$this, $method], $args);
            }
        };

        $directive->hydrate($definitionNode);

        return $directive;
    }
}
