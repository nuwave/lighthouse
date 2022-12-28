<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

class ResolveInfo extends BaseResolveInfo
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public $argumentSet;

    public function __construct(
        FieldDefinition $fieldDefinition,
        iterable $fieldNodes,
        ObjectType $parentType,
        array $path,
        Schema $schema,
        array $fragments,
        $rootValue,
        ?OperationDefinitionNode $operation,
        array $variableValues,
        ArgumentSet $argumentSet
    ) {
        parent::__construct($fieldDefinition, $fieldNodes, $parentType, $path, $schema, $fragments, $rootValue, $operation, $variableValues);
        $this->argumentSet = $argumentSet;
    }

    /**
     * Apply ArgBuilderDirectives and scopes to the builder.
     *
     * @template TBuilder of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder  $builder
     *
     * @phpstan-param  TBuilder  $builder
     *
     * @param  array<string>  $scopes
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder
     *
     * @phpstan-return TBuilder
     */
    public function enhanceBuilder(object $builder, array $scopes, \Closure $directiveFilter = null): object
    {
        return $this->argumentSet->enhanceBuilder($builder, $scopes, $directiveFilter);
    }
}
