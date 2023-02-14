<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Scout\ScoutEnhancer;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

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
        OperationDefinitionNode $operation,
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
     * @param  array<string, mixed>  $args
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder
     *
     * @phpstan-return TBuilder
     */
    public function enhanceBuilder(object $builder, array $scopes, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, \Closure $directiveFilter = null): object
    {
        $argumentSet = $resolveInfo->argumentSet;

        $scoutEnhancer = new ScoutEnhancer($argumentSet, $builder);
        if ($scoutEnhancer->canEnhanceBuilder()) {
            return $scoutEnhancer->enhanceBuilder();
        }

        self::applyArgBuilderDirectives($argumentSet, $builder, $directiveFilter);
        self::applyFieldBuilderDirectives($builder, $root, $args, $context, $resolveInfo);

        foreach ($scopes as $scope) {
            $builder->{$scope}($args);
        }

        return $builder;
    }

    /**
     * Recursively apply the ArgBuilderDirectives onto the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  (\Closure(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective): bool)|null  $directiveFilter
     */
    protected static function applyArgBuilderDirectives(ArgumentSet $argumentSet, object &$builder, \Closure $directiveFilter = null): void
    {
        foreach ($argumentSet->arguments as $argument) {
            $value = $argument->toPlain();

            $filteredDirectives = $argument
                ->directives
                ->filter(Utils::instanceofMatcher(ArgBuilderDirective::class));

            if (null !== $directiveFilter) {
                $filteredDirectives = $filteredDirectives->filter($directiveFilter);
            }

            $filteredDirectives->each(static function (ArgBuilderDirective $argBuilderDirective) use (&$builder, $value): void {
                $builder = $argBuilderDirective->handleBuilder($builder, $value);
            });

            Utils::applyEach(
                static function ($value) use (&$builder, $directiveFilter) {
                    if ($value instanceof ArgumentSet) {
                        self::applyArgBuilderDirectives($value, $builder, $directiveFilter);
                    }
                },
                $argument->value
            );
        }
    }

    /**
     * Apply the FieldBuilderDirectives onto the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  array<string, mixed>  $args
     */
    protected static function applyFieldBuilderDirectives(object &$builder, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): void
    {
        $resolveInfo->argumentSet->directives
            ->filter(Utils::instanceofMatcher(FieldBuilderDirective::class))
            ->each(static function (FieldBuilderDirective $fieldBuilderDirective) use (&$builder, $root, $args, $context, $resolveInfo): void {
                $builder = $fieldBuilderDirective->handleFieldBuilder($builder, $root, $args, $context, $resolveInfo);
            });
    }
}
