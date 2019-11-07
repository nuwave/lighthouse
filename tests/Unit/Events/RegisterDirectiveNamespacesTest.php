<?php

namespace Tests\Unit\Events;

use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Tests\Integration\Events\FieldDirective as TestFieldDirective;
use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;

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

    public function testCanAddAdditionalDirectiveBaseNamespacesThroughEvent(): void
    {
        $directive = $this->directiveFactory->create('foo');

        $this->assertInstanceOf(FooDirective::class, $directive);
    }

    public function testCanOverwriteDefaultDirectiveThroughEvent(): void
    {
        $directive = $this->directiveFactory->create('field');

        $this->assertInstanceOf(TestFieldDirective::class, $directive);
    }
}
