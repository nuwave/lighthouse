<?php

namespace Tests\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Tests\Integration\Events\FieldDirective as TestFieldDirective;
use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;

final class RegisterDirectiveNamespacesTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    protected function getEnvironmentSetUp($app): void
    {
        $dispatcher = $app->make(EventsDispatcher::class);
        assert($dispatcher instanceof EventsDispatcher);

        $dispatcher->listen(
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

    public function testAddAdditionalDirectiveBaseNamespacesThroughEvent(): void
    {
        $directive = $this->directiveLocator->create('foo');

        $this->assertInstanceOf(FooDirective::class, $directive);
    }

    public function testOverwriteDefaultDirectiveThroughEvent(): void
    {
        $directive = $this->directiveLocator->create('field');

        $this->assertInstanceOf(TestFieldDirective::class, $directive);
    }
}
