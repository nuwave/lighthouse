<?php

namespace Nuwave\Lighthouse\CacheControl;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
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
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            StartExecution::class,
            function (StartExecution $startExecution) {
                $cacheControl = $this->app->make(CacheControl::class);
                $typeInfo = new TypeInfo($startExecution->schema);
                Visitor::visit($startExecution->query, Visitor::visitWithTypeInfo($typeInfo, [
                    NodeKind::FIELD => function (FieldNode $node) use ($typeInfo, $cacheControl): void {
                        $field = $typeInfo->getFieldDef();
                        // @phpstan-ignore-next-line can be null, remove ignore with graphql-php 15
                        if (null === $field) {
                            return;
                        }

                        $nodeType = $field->getType();
                        // TODO use getInnermostType() in graphql-php 15
                        while ($nodeType instanceof WrappingType) {
                            $nodeType = $nodeType->getWrappedType();
                        }

                        $parent = $typeInfo->getParentType();
                        assert($parent instanceof CompositeType && $parent instanceof Type);
                        if (RootType::isRootType($parent->name)) {
                            $maxAge = 0;
                        }

                        if (! $nodeType instanceof ScalarType) {
                            $maxAge = 0;
                        }

                        if (isset($field->astNode)) {
                            $cacheControlDirective = ASTHelper::directiveDefinition($field->astNode, 'cacheControl');
                            if (null !== $cacheControlDirective) {
                                $maxAge = ASTHelper::directiveArgValue($cacheControlDirective, 'maxAge') ?? 0;
                                $scope = ASTHelper::directiveArgValue($cacheControlDirective, 'scope') ?? 'PUBLIC';
                            }
                        }

                        if (isset($maxAge)) {
                            $cacheControl->addToMaxAgeList($maxAge);
                        }
                        if (isset($scope)) {
                            $cacheControl->addToScopeList($scope);
                        }
                    },
                ]));
            }
        );

        $dispatcher->listen(
            EndRequest::class,
            function (EndRequest $request): void {
                $cacheControl = $this->app->make(CacheControl::class);

                $maxAge = $cacheControl->calculateMaxAge();
                $response = $request->response;
                $headers = $response->headers;

                if ($maxAge > 0) {
                    $response->setMaxAge($maxAge);
                } else {
                    $headers->addCacheControlDirective('no-cache');
                }

                $headers->addCacheControlDirective($cacheControl->calculateScope());

                $this->app->forgetInstance(CacheControl::class);
            }
        );
    }
}
