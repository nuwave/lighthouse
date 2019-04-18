<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;

abstract class RelationDirective extends BaseDirective
{
    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred {
                return BatchLoader::instance(
                    RelationBatchLoader::class,
                    $resolveInfo->path,
                    $this->toLoaderConstructorArguments($parent, $args, $context, $resolveInfo)
                )->load(
                    $parent->getKey(),
                    ['parent' => $parent]
                );
            }
        );
    }

    /**
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $current
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        $paginationType = $this->directiveArgValue('type');

        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if (! $paginationType) {
            return $current;
        }

        return PaginationManipulator::transformToPaginatedField(
            $paginationType,
            $fieldDefinition,
            $parentType,
            $current,
            $this->directiveArgValue('defaultCount'),
            $this->paginateMaxCount()
        );
    }

    /**
     * Transform resolver args to an array suitable for constructing a BatchLoader.
     *
     * @see \Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return mixed[]
     */
    protected function toLoaderConstructorArguments(Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $constructorArgs = [
            'relationName' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
            'args' => $args,
            'scopes' => $this->directiveArgValue('scopes', []),
            'resolveInfo' => $resolveInfo,
            'paginateMaxCount' => $this->paginateMaxCount(),
        ];

        if ($paginationType = $this->directiveArgValue('type')) {
            $constructorArgs += ['paginationType' => PaginationManipulator::assertValidPaginationType($paginationType)];
        }

        return $constructorArgs;
    }

    /**
     * Get either the specific max or the global setting.
     *
     * @return int|null
     */
    protected function paginateMaxCount(): ?int
    {
        return $this->directiveArgValue('maxCount')
            ?? config('lighthouse.paginate_max_count');
    }
}
