<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tracing::class, static fn (Application $app): Tracing => $app->make(config('lighthouse.tracing.driver')));
    }

    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
        $dispatcher->listen(ManipulateAST::class, static fn (ManipulateAST $manipulateAST) => ASTHelper::attachDirectiveToObjectTypeFields(
            $manipulateAST->documentAST,
            Parser::constDirective('@tracing'),
        ));
        $dispatcher->listen(StartRequest::class, static fn (StartRequest $event) => Container::getInstance()
            ->make(Tracing::class)
            ->handleStartRequest($event));
        $dispatcher->listen(StartExecution::class, static fn (StartExecution $event) => Container::getInstance()
            ->make(Tracing::class)
            ->handleStartExecution($event));
        $dispatcher->listen(BuildExtensionsResponse::class, static fn (BuildExtensionsResponse $event) => Container::getInstance()
            ->make(Tracing::class)
            ->handleBuildExtensionsResponse($event));
    }
}
