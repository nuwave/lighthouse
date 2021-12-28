<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\PaginatedModelsLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\SimpleModelsLoader;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class RelationDirective extends BaseDirective implements FieldResolver
{
    use RelationDirectiveHelpers;

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                $relationName = $this->relation();

                $decorateBuilder = $this->makeBuilderDecorator($resolveInfo);
                $paginationArgs = $this->paginationArgs($args);

                /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
                $relation = $parent->{$relationName}();

                if (
                    config('lighthouse.batchload_relations')
                    // Batch loading joins across both models, thus only works if they are on the same connection
                    && $relation->getParent()->getConnectionName() === $relation->getRelated()->getConnectionName()
                ) {
                    /** @var \Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader $relationBatchLoader */
                    $relationBatchLoader = BatchLoaderRegistry::instance(
                        $this->qualifyPath($args, $resolveInfo),
                        function () use ($relationName, $decorateBuilder, $paginationArgs): RelationBatchLoader {
                            $modelsLoader = null !== $paginationArgs
                                ? new PaginatedModelsLoader($relationName, $decorateBuilder, $paginationArgs)
                                : new SimpleModelsLoader($relationName, $decorateBuilder);

                            return new RelationBatchLoader($modelsLoader);
                        }
                    );

                    return $relationBatchLoader->load($parent);
                }

                $decorateBuilder($relation);

                return null !== $paginationArgs
                    ? $paginationArgs->applyToBuilder($relation)
                    : $relation->getResults();
            }
        );

        return $fieldValue;
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $paginationType = $this->paginationType();

        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if (null === $paginationType) {
            return;
        }

        $paginationManipulator = new PaginationManipulator($documentAST);
        $paginationManipulator->transformToPaginatedField(
            $paginationType,
            $fieldDefinition,
            $parentType,
            $this->paginationDefaultCount(),
            $this->paginationMaxCount(),
            $this->edgeType($documentAST)
        );
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function edgeType(DocumentAST $documentAST): ?ObjectTypeDefinitionNode
    {
        if ($edgeTypeName = $this->directiveArgValue('edgeType')) {
            $edgeType = $documentAST->types[$edgeTypeName] ?? null;
            if (! $edgeType instanceof ObjectTypeDefinitionNode) {
                throw new DefinitionException(
                    "The edgeType argument on {$this->nodeName()} must reference an existing object type definition."
                );
            }

            return $edgeType;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function paginationArgs(array $args): ?PaginationArgs
    {
        $paginationType = $this->paginationType();

        return null !== $paginationType
            ? PaginationArgs::extractArgs($args, $paginationType, $this->paginationMaxCount())
            : null;
    }

    protected function paginationType(): ?PaginationType
    {
        $type = $this->directiveArgValue('type');

        return null !== $type
            ? new PaginationType($type)
            : null;
    }

    protected function paginationMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount')
            ?? config('lighthouse.pagination.max_count');
    }

    protected function paginationDefaultCount(): ?int
    {
        return $this->directiveArgValue('defaultCount')
            ?? config('lighthouse.pagination.default_count');
    }
}
