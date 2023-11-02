<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
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
        $this->app->scoped(Tracing::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
        $dispatcher->listen(ManipulateAST::class, static fn (ManipulateAST $manipulateAST) => ASTHelper::attachDirectiveToObjectTypeFields(
            $manipulateAST->documentAST,
            Parser::constDirective('@tracing'),
        ));
        $dispatcher->listen(StartRequest::class, fn (StartRequest $event) => Container::getInstance()->make(Tracing::class)->handleStartRequest($event));
        $dispatcher->listen(StartExecution::class, fn (StartExecution $event) => Container::getInstance()->make(Tracing::class)->handleStartExecution($event));
        $dispatcher->listen(BuildExtensionsResponse::class, fn (BuildExtensionsResponse $event) => Container::getInstance()->make(Tracing::class)->handleBuildExtensionsResponse($event));
    }
}
