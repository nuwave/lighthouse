<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Scout\ScoutEnhancer;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class ResolveInfo extends BaseResolveInfo
{
    public function __construct(
        BaseResolveInfo $baseResolveInfo,
        public ArgumentSet $argumentSet,
    ) {
        parent::__construct(
            fieldDefinition: $baseResolveInfo->fieldDefinition,
            fieldNodes: $baseResolveInfo->fieldNodes,
            parentType: $baseResolveInfo->parentType,
            path: $baseResolveInfo->path,
            schema: $baseResolveInfo->schema,
            fragments: $baseResolveInfo->fragments,
            rootValue: $baseResolveInfo->rootValue,
            operation: $baseResolveInfo->operation,
            variableValues: $baseResolveInfo->variableValues,
        );
    }

    /**
     * Apply ArgBuilderDirectives and scopes to the builder.
     *
     * @param  array<string>  $scopes
     * @param  array<string, mixed>  $args
     * @param  (callable(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective|\Nuwave\Lighthouse\Scout\ScoutBuilderDirective): bool)|null  $directiveFilter
     */
    public function enhanceBuilder(
        Builder|ScoutBuilder $builder,
        array $scopes,
        mixed $root,
        array $args,
        GraphQLContext $context,
        ?callable $directiveFilter = null,
    ): Builder|ScoutBuilder {
        $scoutEnhancer = new ScoutEnhancer($this->argumentSet, $builder);
        if ($scoutEnhancer->canEnhanceBuilder()) {
            return $scoutEnhancer->enhanceBuilder($directiveFilter);
        }

        assert($builder instanceof Builder, 'Scout builders are handled by the ScoutEnhancer.');

        self::applyArgBuilderDirectives($this->argumentSet, $builder, $directiveFilter);
        $this->applyFieldBuilderDirectives($builder, $root, $args, $context);

        foreach ($scopes as $scope) {
            $builder->{$scope}($args);
        }

        return $builder;
    }

    /**
     * Would the builder be enhanced in any way?
     *
     * @param  array<string>  $scopes
     * @param  array<string, mixed>  $args
     * @param  (callable(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective): bool)|null  $directiveFilter
     */
    public function wouldEnhanceBuilder(
        Builder|ScoutBuilder $builder,
        array $scopes,
        mixed $root,
        array $args,
        GraphQLContext $context,
        ?callable $directiveFilter = null,
    ): bool {
        return (new ScoutEnhancer($this->argumentSet, $builder))->wouldEnhanceBuilder()
            || self::wouldApplyArgBuilderDirectives($this->argumentSet, $directiveFilter)
            || $this->wouldApplyFieldBuilderDirectives()
            || $scopes !== [];
    }

    /**
     * Recursively apply the ArgBuilderDirectives onto the builder.
     *
     * @param  (callable(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective): bool)|null  $directiveFilter
     */
    protected static function applyArgBuilderDirectives(
        ArgumentSet $argumentSet,
        Builder &$builder,
        ?callable $directiveFilter = null,
    ): void {
        foreach ($argumentSet->arguments as $argument) {
            $value = $argument->toPlain();

            foreach ($argument->directives as $directive) {
                if (! $directive instanceof ArgBuilderDirective) {
                    continue;
                }

                if ($directiveFilter !== null && ! $directiveFilter($directive)) {
                    continue;
                }

                $builder = $directive->handleBuilder($builder, $value);
            }

            Utils::applyEach(
                static function ($value) use (&$builder, $directiveFilter): void {
                    if ($value instanceof ArgumentSet) {
                        self::applyArgBuilderDirectives($value, $builder, $directiveFilter);
                    }
                },
                $argument->value,
            );
        }
    }

    /**
     * Would there be any ArgBuilderDirectives to apply to the builder?
     *
     * @param  (callable(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective): bool)|null  $directiveFilter
     */
    protected static function wouldApplyArgBuilderDirectives(
        ArgumentSet $argumentSet,
        ?callable $directiveFilter = null,
    ): bool {
        foreach ($argumentSet->arguments as $argument) {
            $filteredDirectives = $argument
                ->directives
                ->filter(Utils::instanceofMatcher(ArgBuilderDirective::class));

            if ($directiveFilter !== null) {
                // @phpstan-ignore-next-line PHPStan does not get this list is filtered for ArgBuilderDirective
                $filteredDirectives = $filteredDirectives->filter($directiveFilter);
            }

            if ($filteredDirectives->isNotEmpty()) {
                return true;
            }

            $valueOrValues = $argument->value;
            if ($valueOrValues instanceof ArgumentSet) {
                return self::wouldApplyArgBuilderDirectives($valueOrValues, $directiveFilter);
            }

            if (is_array($valueOrValues)) {
                foreach ($valueOrValues as $value) {
                    if ($value instanceof ArgumentSet) {
                        $wouldApply = self::wouldApplyArgBuilderDirectives($value, $directiveFilter);
                        if ($wouldApply) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Apply the FieldBuilderDirectives onto the builder.
     *
     * @param  array<string, mixed>  $args
     */
    protected function applyFieldBuilderDirectives(Builder &$builder, mixed $root, array $args, GraphQLContext $context): void
    {
        foreach ($this->fieldBuilderDirectives() as $fieldBuilderDirective) {
            $builder = $fieldBuilderDirective->handleFieldBuilder($builder, $root, $args, $context, $this);
        }
    }

    /** Would there be any FieldBuilderDirectives to apply to the builder? */
    protected function wouldApplyFieldBuilderDirectives(): bool
    {
        return $this->fieldBuilderDirectives()
            ->isNotEmpty();
    }

    /** @return Collection<int, \Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective> */
    protected function fieldBuilderDirectives(): Collection
    {
        // @phpstan-ignore-next-line filter is not understood
        return $this->argumentSet
            ->directives
            ->filter(Utils::instanceofMatcher(FieldBuilderDirective::class));
    }
}
