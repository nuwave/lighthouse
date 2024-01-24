<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\PaginatedModelsLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\SimpleModelsLoader;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class RelationDirective extends BaseDirective implements FieldResolver
{
    use RelationDirectiveHelpers;

    /** @var array<string, mixed> */
    protected array $lighthouseConfig = [];

    public function __construct(
        protected ConnectionResolverInterface $database,
        ConfigRepository $configRepository,
    ) {
        $this->lighthouseConfig = $configRepository->get('lighthouse');
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $relationName = $this->relation();

        return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($relationName) {
            $decorateBuilder = $this->makeBuilderDecorator($parent, $args, $context, $resolveInfo);
            $paginationArgs = $this->paginationArgs($args, $resolveInfo);

            $relation = $parent->{$relationName}();
            assert($relation instanceof Relation);

            // We can shortcut the resolution if the client only queries for a foreign key
            // that we know to be present on the parent model.
            if (
                $this->lighthouseConfig['shortcut_foreign_key_selection']
                && ['id' => true] === $resolveInfo->getFieldSelection()
                && $relation instanceof BelongsTo
                && $args === []
            ) {
                $id = $parent->getAttribute($relation->getForeignKeyName());

                return $id === null
                    ? null
                    : ['id' => $id];
            }

            if (
                $this->lighthouseConfig['batchload_relations']
                // Batch loading joins across both models, thus only works if they are on the same connection
                && $this->isSameConnection($relation)
            ) {
                $relationBatchLoader = BatchLoaderRegistry::instance(
                    $this->qualifyPath($args, $resolveInfo),
                    static fn (): RelationBatchLoader => new RelationBatchLoader(
                        $paginationArgs === null
                            ? new SimpleModelsLoader($relationName, $decorateBuilder)
                            : new PaginatedModelsLoader($relationName, $decorateBuilder, $paginationArgs),
                    ),
                );

                return $relationBatchLoader->load($parent);
            }

            $decorateBuilder($relation);

            return $paginationArgs !== null
                ? $paginationArgs->applyToBuilder($relation)
                : $relation->getResults();
        };
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $paginationType = $this->paginationType();

        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if ($paginationType === null) {
            return;
        }

        $paginationManipulator = new PaginationManipulator($documentAST);

        $relatedModelName = ASTHelper::modelName($fieldDefinition);
        if (is_string($relatedModelName)) {
            try {
                $modelClass = $this->namespaceModelClass($relatedModelName);
                $paginationManipulator->setModelClass($modelClass);
            } catch (DefinitionException) {
                /** @see \Tests\Integration\Schema\Directives\HasManyDirectiveTest::testDoesNotRequireModelClassForPaginatedHasMany() */
            }
        }

        $paginationManipulator->transformToPaginatedField(
            $paginationType,
            $fieldDefinition,
            $parentType,
            $this->paginationDefaultCount(),
            $this->paginationMaxCount(),
            $this->edgeType($documentAST),
        );
    }

    protected function edgeType(DocumentAST $documentAST): ?ObjectTypeDefinitionNode
    {
        if ($edgeTypeName = $this->directiveArgValue('edgeType')) {
            $edgeType = $documentAST->types[$edgeTypeName] ?? null;
            if (! $edgeType instanceof ObjectTypeDefinitionNode) {
                throw new DefinitionException("The `edgeType` argument of @{$this->name()} on {$this->nodeName()} must reference an existing object type definition.");
            }

            return $edgeType;
        }

        return null;
    }

    /** @param  array<string, mixed>  $args */
    protected function paginationArgs(array $args, ResolveInfo $resolveInfo): ?PaginationArgs
    {
        $paginationType = $this->paginationType();

        return $paginationType !== null
            ? PaginationArgs::extractArgs($args, $resolveInfo, $paginationType, $this->paginationMaxCount())
            : null;
    }

    protected function paginationType(): ?PaginationType
    {
        $type = $this->directiveArgValue('type');

        return $type !== null
            ? new PaginationType($type)
            : null;
    }

    protected function paginationMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount', $this->lighthouseConfig['pagination']['max_count']);
    }

    protected function paginationDefaultCount(): ?int
    {
        return $this->directiveArgValue('defaultCount', $this->lighthouseConfig['pagination']['default_count']);
    }

    /** @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $relation */
    protected function isSameConnection(Relation $relation): bool
    {
        $default = $this->database->getDefaultConnection();

        $parent = $relation->getParent()->getConnectionName() ?? $default;
        $related = $relation->getRelated()->getConnectionName() ?? $default;

        return $parent === $related;
    }
}
