<?php

namespace Tests\Integration\Events;

use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Tests\Integration\Events\FieldDirective as TestFieldDirective;

class RegisterDirectiveNamespacesTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    protected function getEnvironmentSetUp($app)
    {
        $app->make('events')->listen(
            RegisterDirectiveNamespaces::class,
            function (): array {
                return [
                    'Tests\\Utils\\Directives',
                    'Tests\\Integration\\Events',
                ];
            }
        );

        $this->directiveFactory = $app->make(DirectiveFactory::class);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * @test
     */
    public function itCanAddAdditionalDirectiveBaseNamespacesThroughEvent(): void
    {
        $directive = $this->directiveFactory->create('foo');

        $this->assertInstanceOf(FooDirective::class, $directive);
    }

    /**
     * @test
     */
    public function itCanOverwriteDefaultDirectiveThroughEvent(): void
    {
        $directive = $this->directiveFactory->create('field');

        $this->assertInstanceOf(TestFieldDirective::class, $directive);
    }
}
