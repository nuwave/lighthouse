<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Database\Query\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * For directives that query a model or a custom builder through the `builder` argument.
 */
trait HasBuilderArgument
{
    /** @param  array<string, mixed>  $args */
    protected function makeBuilder(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder|ScoutBuilder
    {
        if (! $this->directiveHasArgument('builder')) {
            return $this->getModelClass()::query();
        }

        $builder = $this->getResolverFromArgument('builder')($root, $args, $context, $resolveInfo);
        assert(
            $builder instanceof Builder || $builder instanceof ScoutBuilder,
            "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder or Relation.",
        );

        return $builder;
    }
}
