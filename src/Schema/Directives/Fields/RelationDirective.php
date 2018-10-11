<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;

abstract class RelationDirective extends BaseDirective
{
    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws \Exception
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            function (Model $parent, array $args, $context, ResolveInfo $resolveInfo) {
                return BatchLoader::instance(
                    RelationBatchLoader::class,
                    $resolveInfo->path,
                    $this->getLoaderConstructorArguments($parent, $args, $context, $resolveInfo)
                )->load(
                    $parent->getKey(),
                    ['parent' => $parent]
                );
            }
        );
    }
    
    /**
     * @param FieldDefinitionNode $fieldDefinition
     * @param ObjectTypeDefinitionNode $parentType
     * @param DocumentAST $current
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(FieldDefinitionNode $fieldDefinition, ObjectTypeDefinitionNode $parentType, DocumentAST $current): DocumentAST
    {
        $paginationType = $this->directiveArgValue('type');
        
        // We default to not changing the field if no pagination type is set explicitly.
        // This makes sense for relations, as there should not be too many entries.
        if(! $paginationType) {
            return $current;
        }
        
        return PaginationManipulator::transformToPaginatedField($paginationType, $fieldDefinition, $parentType, $current);
    }
    
    /**
     * @param Model $parent
     * @param array $args
     * @param null $context
     * @param ResolveInfo $resolveInfo
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getLoaderConstructorArguments(Model $parent, array $args, $context, ResolveInfo $resolveInfo): array
    {
        $constructorArgs =  [
            'scopes' => $this->directiveArgValue('scopes', []),
            'args' => $args,
            'relationName' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
        ];
        
        if($paginationType = $this->directiveArgValue('type')){
            $constructorArgs += ['paginationType' => PaginationManipulator::assertValidPaginationType($paginationType)];
        }
        
        return $constructorArgs;
    }
}
