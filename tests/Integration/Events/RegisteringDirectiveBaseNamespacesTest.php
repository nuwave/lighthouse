<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Events\RegisteringDirectiveBaseNamespaces;
use Tests\Utils\Directives\FieldDirective as TestFieldDirective;

class RegisteringDirectiveBaseNamespacesTest extends TestCase
{
    /**
     * @var DirectiveFactory
     */
    protected $directiveFactory;

    protected function getEnvironmentSetUp($app)
    {
        $app->make('events')->listen(
            RegisteringDirectiveBaseNamespaces::class,
            function () {
                return ['Tests\\Utils\\Directives'];
            }
        );

        $this->directiveFactory = $app->make(DirectiveFactory::class);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * @test
     */
    public function itCanAddAdditionalDirectiveBaseNamespacesThroughEvent()
    {
        $directive = $this->directiveFactory->create('foo');
        $this->assertInstanceOf(FooDirective::class, $directive);
    }

    /**
     * @test
     */
    public function itCanOverwriteDefaultDirectiveThroughEvent()
    {
        $directive = $this->directiveFactory->create('field');
        $this->assertInstanceOf(TestFieldDirective::class, $directive);
    }
}
