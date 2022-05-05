<?php

namespace Nuwave\Lighthouse\CacheControl;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\TypeInfo;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;

class CacheControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheControl::class);
    }

    public function boot(Dispatcher $dispatcher, CacheControl $cacheControl): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            StartExecution::class,
            function (StartExecution $startExecution) use ($cacheControl) {
                $typeInfo = new TypeInfo($startExecution->schema);
                Visitor::visit($startExecution->query, Visitor::visitWithTypeInfo($typeInfo, [
                    NodeKind::FIELD => function (FieldNode $node) use ($typeInfo, $cacheControl): void {
                        $field = $typeInfo->getFieldDef();
                        // @phpstan-ignore-next-line can be null, remove ignore with graphql-php 15
                        if (null === $field) {
                            return;
                        }

                        $nodeType = $field->getType();
                        if ($nodeType instanceof NonNull || $nodeType instanceof ListOfType) {
                            do {
                                $nodeType = $nodeType->getOfType();
                            } while ($nodeType instanceof NonNull || $nodeType instanceof ListOfType);
                        }

                        if (! $nodeType instanceof ScalarType) {
                            $maxAge = 0;
                        }

                        if (isset($field->astNode)) {
                            $cacheControlDirective = ASTHelper::directiveDefinition('cacheControl', $field->astNode);
                            if ($cacheControlDirective !== null) {
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
            function (EndRequest $request) use ($cacheControl): void {
                $maxAge = $cacheControl->calculateMaxAge();
                if (0 != $maxAge) {
                    $request->response->setMaxAge($maxAge);
                } else {
                    $request->response->headers->addCacheControlDirective('no-cache');
                }
                $request->response->headers->addCacheControlDirective($cacheControl->calculateScope());

                $this->app->forgetInstance(CacheControl::class);
            }
        );
    }
}
