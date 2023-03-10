<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\CacheControl;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\TypeInfo;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\RootType;

class CacheControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheControl::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
        $dispatcher->listen(StartExecution::class, function (StartExecution $startExecution): void {
            $typeInfo = new TypeInfo($startExecution->schema);
            $cacheControl = $this->app->make(CacheControl::class);

            // @phpstan-ignore-next-line NodeVisitor does not know about the mapping between node kind and node type
            $visitorWithTypeInfo = Visitor::visitWithTypeInfo($typeInfo, [
                NodeKind::FIELD => static function (FieldNode $_) use ($typeInfo, $cacheControl): void {
                    $field = $typeInfo->getFieldDef();
                    if (null === $field) {
                        return;
                    }

                    $cacheControlDirective = isset($field->astNode)
                        ? ASTHelper::directiveDefinition($field->astNode, 'cacheControl')
                        : null;
                    if (null !== $cacheControlDirective) {
                        if (! ASTHelper::directiveArgValue($cacheControlDirective, 'inheritMaxAge')) {
                            $cacheControl->addMaxAge(
                                ASTHelper::directiveArgValue($cacheControlDirective, 'maxAge') ?? 0,
                            );
                        }

                        if ('PRIVATE' === ASTHelper::directiveArgValue($cacheControlDirective, 'scope')) {
                            $cacheControl->setPrivate();
                        }
                    } else {
                        $parent = $typeInfo->getParentType();
                        assert($parent instanceof NamedType);

                        if (RootType::isRootType($parent->name())) {
                            $cacheControl->addMaxAge(0);
                            $cacheControl->setPrivate();
                        } else {
                            $nodeType = $field->getType();
                            if ($nodeType instanceof WrappingType) {
                                $nodeType = $nodeType->getInnermostType();
                            }

                            if (! $nodeType instanceof ScalarType) {
                                $cacheControl->addMaxAge(0);
                            }
                        }
                    }
                },
            ]);
            Visitor::visit($startExecution->query, $visitorWithTypeInfo);
        });
        $dispatcher->listen(EndRequest::class, function (EndRequest $request): void {
            $cacheControl = $this->app->make(CacheControl::class);

            $maxAge = $cacheControl->maxAge();
            $response = $request->response;
            $headers = $response->headers;

            if ($maxAge > 0) {
                $response->setMaxAge($maxAge);
            } else {
                $headers->addCacheControlDirective('no-cache');
            }

            $headers->addCacheControlDirective($cacheControl->scope());

            $this->app->forgetInstance(CacheControl::class);
        });
    }
}
