<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Pagination\PaginationUtils;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Pagination\PaginationManipulator;
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
                $constructorArgs = [
                    'relationName' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
                    'args' => $args,
                    'scopes' => $this->directiveArgValue('scopes', []),
                    'resolveInfo' => $resolveInfo,
                ];

                if ($paginationType = $this->paginationType()) {
                    /** @var int $first */
                    /** @var int $page */
                    [$first, $page] = PaginationUtils::extractArgs($args, $paginationType, $this->paginateMaxCount());

                    $constructorArgs += [
                        'first' => $first,
                        'page' => $page,
                    ];
                }

                return BatchLoader
                    ::instance(
                        RelationBatchLoader::class,
                        $resolveInfo->path,
                        $constructorArgs
                    )
                    ->load(
                        $parent->getKey(),
                        ['parent' => $parent]
                    );
            }
        );
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @return void
     */
    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType): void
    {
        $paginationType = $this->paginationType();

        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if (! $paginationType) {
            return;
        }

        $paginationManipulator = new PaginationManipulator($documentAST);
        $paginationManipulator->transformToPaginatedField(
            $paginationType,
            $fieldDefinition,
            $parentType,
            $this->directiveArgValue('defaultCount'),
            $this->paginateMaxCount(),
            $this->edgeType($documentAST)
        );
    }

    protected function paginationType(): ?PaginationType
    {
        if ($paginationType = $this->directiveArgValue('type')) {
            return new PaginationType($paginationType);
        }

        return null;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|null
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function edgeType(DocumentAST $documentAST): ?ObjectTypeDefinitionNode
    {
        if ($edgeType = $this->directiveArgValue('edgeType')) {
            if (! isset($documentAST->types[$edgeType])) {
                throw new DirectiveException(
                    'The edgeType argument on '.$this->definitionNode->name->value.' must reference an existing type definition'
                );
            }

            return $documentAST->types[$edgeType];
        }

        return null;
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
