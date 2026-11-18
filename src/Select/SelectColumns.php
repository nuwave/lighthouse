<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Cache\CacheDirective;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\AggregateDirective;
use Nuwave\Lighthouse\Schema\Directives\CountDirective;
use Nuwave\Lighthouse\Schema\Directives\RelationDirective;
use Nuwave\Lighthouse\Schema\Directives\WithRelationDirective;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

class SelectColumns
{
    /** @var \WeakMap<\GraphQL\Language\AST\ObjectTypeDefinitionNode, bool> */
    protected \WeakMap $optsInByType;

    public function __construct(
        protected DirectiveLocator $directiveLocator,
    ) {
        $this->optsInByType = new \WeakMap();
    }

    /**
     * Leaves the builder unchanged if the queried type does not use @select,
     * or if Lighthouse can not determine the columns safely.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|\Laravel\Scout\Builder  $builder
     */
    public function applyTo(QueryBuilder|EloquentBuilder|Relation|ScoutBuilder $builder, ResolveInfo $resolveInfo, ?PaginationType $paginationType = null): void
    {
        if (! $builder instanceof EloquentBuilder) {
            return;
        }

        if ($builder->getQuery()->columns !== null) {
            return;
        }

        $itemsPath = $paginationType?->itemsPath() ?? [];

        $type = $this->optedInItemsType($resolveInfo->returnType, $itemsPath);
        if ($type === null) {
            return;
        }

        $fieldSelection = $resolveInfo->getFieldSelection(count($itemsPath));
        foreach ($itemsPath as $fieldName) {
            $fieldSelection = $fieldSelection[$fieldName] ?? [];
        }

        $columns = $this->columns($type, $fieldSelection, $builder->getModel(), array_keys($builder->getEagerLoads()));
        if ($columns !== null) {
            $builder->select($columns);
        }
    }

    public function optsIn(ObjectTypeDefinitionNode $type): bool
    {
        return $this->optsInByType[$type] ??= $this->computeOptsIn($type);
    }

    protected function computeOptsIn(ObjectTypeDefinitionNode $type): bool
    {
        if ($this->selectDirective($type) !== null) {
            return true;
        }

        foreach ($type->fields as $field) {
            if ($this->selectDirective($field) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Includes fields whose relation directive resolves them. */
    public function declaresColumns(FieldDefinitionNode $field): bool
    {
        if ($this->selectDirective($field) !== null) {
            return true;
        }

        foreach ($field->directives as $directive) {
            $directiveClass = $this->directiveLocator->resolve($directive->name->value);
            // A builder receives the parent and may read any of its attributes
            $usesBuilder = ASTHelper::directiveArgValue($directive, 'builder') !== null;
            if ($this->resolvesRelation($directiveClass) && ! $usesBuilder) {
                return true;
            }
        }

        return false;
    }

    /** Matches by class, so an app's own directive named @select is left alone. */
    public function selectDirective(ObjectTypeDefinitionNode|FieldDefinitionNode $node): ?DirectiveNode
    {
        foreach ($node->directives as $directive) {
            $directiveClass = $this->directiveLocator->resolve($directive->name->value);
            if (is_a($directiveClass, SelectDirective::class, true)) {
                return $directive;
            }
        }

        return null;
    }

    /** @param  array<int, string>  $itemsPath */
    protected function optedInItemsType(Type $returnType, array $itemsPath): ?ObjectType
    {
        $type = Type::getNamedType($returnType);

        foreach ($itemsPath as $fieldName) {
            $field = $type instanceof ObjectType
                ? $type->findField($fieldName)
                : null;
            if ($field === null) {
                return null;
            }

            $type = Type::getNamedType($field->getType());
        }

        if (! $type instanceof ObjectType) {
            return null;
        }

        $typeNode = $type->astNode;
        if ($typeNode === null || ! $this->optsIn($typeNode)) {
            return null;
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $fieldSelection
     * @param  array<int, string>  $eagerLoads  relations the builder already loads, e.g. from the model's $with
     *
     * @return array<int, string>|null
     */
    protected function columns(ObjectType $type, array $fieldSelection, Model $model, array $eagerLoads): ?array
    {
        $keyName = $model->getKeyName();
        // @phpstan-ignore function.alreadyNarrowedType (composite key packages return an array)
        if (! is_string($keyName)) {
            return null;
        }

        $columns = [$keyName];

        $typeNode = $type->astNode;
        if ($typeNode !== null) {
            array_push($columns, ...$this->selectDirectiveColumns($typeNode));
        }

        $fieldNames = array_keys($fieldSelection);
        $cacheKeyFieldName = $this->cacheKeyFieldName($type);
        if ($cacheKeyFieldName !== null) {
            // @cache keys results by this field, so it must be loaded even when not queried
            $fieldNames[] = $cacheKeyFieldName;
        }

        foreach ($fieldNames as $fieldName) {
            if ($fieldName === '__typename') {
                continue;
            }

            $fieldColumns = $this->fieldColumns($type->getField($fieldName), $model);
            if ($fieldColumns === null) {
                return null;
            }

            array_push($columns, ...$fieldColumns);
        }

        foreach ($eagerLoads as $eagerLoad) {
            $parentKeys = $this->parentKeys($model, $eagerLoad);
            if ($parentKeys === null) {
                return null;
            }

            array_push($columns, ...$parentKeys);
        }

        return array_values(array_unique(array_map(
            static fn (string $column): string => $model->qualifyColumn($column),
            $columns,
        )));
    }

    protected function cacheKeyFieldName(ObjectType $type): ?string
    {
        $typeNode = $type->astNode;
        if ($typeNode === null || ! $this->usesCache($typeNode)) {
            return null;
        }

        $cacheKey = (new TypeValue($typeNode))->cacheKey();

        foreach ($typeNode->fields as $field) {
            if (ASTHelper::internalFieldName($field) === $cacheKey) {
                return $field->name->value;
            }
        }

        return null;
    }

    protected function usesCache(ObjectTypeDefinitionNode $type): bool
    {
        foreach ($type->fields as $field) {
            foreach ($field->directives as $directive) {
                $directiveClass = $this->directiveLocator->resolve($directive->name->value);
                if (is_a($directiveClass, CacheDirective::class, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int, string>|null */
    protected function fieldColumns(FieldDefinition $field, Model $model): ?array
    {
        $fieldNode = $field->astNode;
        if ($fieldNode === null || ! $this->declaresColumns($fieldNode)) {
            return null;
        }

        $columns = $this->selectDirectiveColumns($fieldNode);

        foreach ($fieldNode->directives as $directive) {
            $relationName = $this->relationName($directive, $fieldNode);
            if ($relationName === null) {
                continue;
            }

            $parentKeys = $this->parentKeys($model, $relationName);
            if ($parentKeys === null) {
                return null;
            }

            array_push($columns, ...$parentKeys);
        }

        return $columns;
    }

    /** @return array<int, string> */
    protected function selectDirectiveColumns(ObjectTypeDefinitionNode|FieldDefinitionNode $node): array
    {
        $selectDirective = $this->selectDirective($node);

        return $selectDirective !== null
            ? ASTHelper::directiveArgValue($selectDirective, 'columns')
            : [];
    }

    /** The directive resolves the field without reading attributes of the parent. */
    protected function resolvesRelation(string $directiveClass): bool
    {
        return is_a($directiveClass, RelationDirective::class, true)
            || is_a($directiveClass, CountDirective::class, true)
            || is_a($directiveClass, AggregateDirective::class, true);
    }

    protected function relationName(DirectiveNode $directive, FieldDefinitionNode $field): ?string
    {
        $directiveClass = $this->directiveLocator->resolve($directive->name->value);
        $relationArgument = ASTHelper::directiveArgValue($directive, 'relation');

        if (is_a($directiveClass, RelationDirective::class, true) || is_a($directiveClass, WithRelationDirective::class, true)) {
            return $relationArgument ?? $field->name->value;
        }

        // Without a relation, they aggregate a whole table and need no parent columns
        if ($this->resolvesRelation($directiveClass)) {
            return $relationArgument;
        }

        return null;
    }

    /**
     * The columns of the parent model that loading the relation needs.
     *
     * @return array<int, string>|null null for relation types we do not know
     */
    protected function parentKeys(Model $model, string $relationName): ?array
    {
        // @with may load nested relations, only the first one is loaded from the parent
        $parentRelationName = explode('.', $relationName)[0];
        $relation = Relation::noConstraints(static fn (): mixed => $model->{$parentRelationName}());

        return match (true) {
            $relation instanceof MorphTo => [$relation->getForeignKeyName(), $relation->getMorphType()],
            $relation instanceof BelongsTo => [$relation->getForeignKeyName()],
            $relation instanceof HasOneOrMany => [$relation->getLocalKeyName()],
            $relation instanceof BelongsToMany => [$relation->getParentKeyName()],
            $relation instanceof HasManyThrough, $relation instanceof HasOneThrough => [$relation->getLocalKeyName()],
            default => null,
        };
    }
}
