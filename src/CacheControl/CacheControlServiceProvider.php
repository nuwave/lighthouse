<?php

namespace Nuwave\Lighthouse\CacheControl;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;
use GraphQL\Utils\TypeInfo;
use Illuminate\Contracts\Events\Dispatcher;
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
            function (StartExecution $StartExecution) use ($cacheControl) {
                $schema = $StartExecution->schema;
                $typeInfo = new TypeInfo($schema);
                $valueNode = $StartExecution->query->definitions;
                Visitor::visit($valueNode, Visitor::visitWithTypeInfo($typeInfo, [
                    NodeKind::FIELD => function (FieldNode $node) use ($schema, $typeInfo, $cacheControl): void {
                        $field = $typeInfo->getFieldDef();
                        // @phpstan-ignore-next-line can be null, remove ignore with graphql-php 15
                        if (null === $field) {
                            return;
                        }

                        $nodeType = AST::typeFromAST($schema, $field->astNode->type);
                        if ($nodeType instanceof (NonNull::class) || $nodeType instanceof (ListOfType::class)) {
                            do {
                                $nodeType = $nodeType->getOfType();
                            } while ($nodeType instanceof (NonNull::class) || $nodeType instanceof (ListOfType::class));
                        }

                        if (!$nodeType instanceof (ScalarType::class)) {
                            $maxAge = 0;
                        }

                        $cacheControlDirective = collect($field->astNode->directives)->where('name.value', 'cacheControl')->first();
                        if (!is_null($cacheControlDirective) && key_exists('arguments', $cacheControlDirective->toArray())) {
                            $maxAge = collect($cacheControlDirective->arguments)->where('name.value', 'maxAge')->pluck('value.value')->first() ?? 0;
                            $scope = collect($cacheControlDirective->arguments)->where('name.value', 'scope')->pluck('value.value')->first() ?? 'PUBLIC';
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
                $request->response->setCache($cacheControl->makeHeaderOptions());
                $this->app->forgetInstance(CacheControl::class);
            }
        );
    }
}
