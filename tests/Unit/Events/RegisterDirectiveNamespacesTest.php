<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Tests\Integration\Events\FieldDirective as TestFieldDirective;
use Tests\TestCase;
use Tests\Utils\Directives\FooDirective;

final class RegisterDirectiveNamespacesTest extends TestCase
{
    protected DirectiveLocator $directiveLocator;

    protected function getEnvironmentSetUp($app): void
    {
        $dispatcher = $app->make(Dispatcher::class);
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): array => [
            'Tests\\Utils\\Directives',
            'Tests\\Integration\\Events',
        ]);

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
