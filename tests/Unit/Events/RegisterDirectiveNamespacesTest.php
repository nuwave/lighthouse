<?php

namespace Tests\Unit\Events;

use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Tests\Integration\Events\FieldDirective as TestFieldDirective;
use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;

class RegisterDirectiveNamespacesTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    protected function getEnvironmentSetUp($app): void
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

        $this->directiveLocator = $app->make(DirectiveLocator::class);

        parent::getEnvironmentSetUp($app);
    }

    public function testCanAddAdditionalDirectiveBaseNamespacesThroughEvent(): void
    {
        $directive = $this->directiveLocator->create('foo');

        $this->assertInstanceOf(FooDirective::class, $directive);
    }

    public function testCanOverwriteDefaultDirectiveThroughEvent(): void
    {
        $directive = $this->directiveLocator->create('field');

        $this->assertInstanceOf(TestFieldDirective::class, $directive);
    }
}
