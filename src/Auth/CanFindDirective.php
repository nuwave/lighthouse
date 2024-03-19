<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\ClientSafeModelNotFoundException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class CanFindDirective extends BaseCanDirective
{
    public static function definition(): string
    {
        $commonArguments = BaseCanDirective::commonArguments();
        $commonTypes = BaseCanDirective::commonTypes();

        return /** @lang GraphQL */ <<<GRAPHQL
{$commonTypes}

"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Query for specific model instances to check the policy against, using primary key(s) from specified argument.
"""
directive @canFind(
{$commonArguments}

  """
  Specify the name of the field argument that contains its primary key(s).

  You may pass the string in dot notation to use nested inputs.
  """
  find: String!

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Should the query fail when the models of `find` were not found?
  """
  findOrFail: Boolean! = true

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed
    {
        foreach ($this->modelsToCheck($root, $args, $context, $resolveInfo) as $model) {
            $authorize($model);
        }

        return $resolver($root, $args, $context, $resolveInfo);
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return iterable<\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function modelsToCheck(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): iterable
    {
        $find = $this->directiveArgValue('find');
        $findValue = Arr::get($args, $find)
            ?? throw self::missingKeyToFindModel($find);

        $queryBuilder = $this->getModelClass()::query();

        $argumentSetDirectives = $resolveInfo->argumentSet->directives;
        $directivesContainsForceDelete = $argumentSetDirectives->contains(
            Utils::instanceofMatcher(ForceDeleteDirective::class),
        );
        if ($directivesContainsForceDelete) {
            /** @see \Illuminate\Database\Eloquent\SoftDeletes */
            // @phpstan-ignore-next-line because it involves mixins
            $queryBuilder->withTrashed();
        }

        $directivesContainsRestore = $argumentSetDirectives->contains(
            Utils::instanceofMatcher(RestoreDirective::class),
        );
        if ($directivesContainsRestore) {
            /** @see \Illuminate\Database\Eloquent\SoftDeletes */
            // @phpstan-ignore-next-line because it involves mixins
            $queryBuilder->onlyTrashed();
        }

        try {
            $enhancedBuilder = $resolveInfo->enhanceBuilder(
                $queryBuilder,
                $this->directiveArgValue('scopes', []),
                $root,
                $args,
                $context,
                $resolveInfo,
                Utils::instanceofMatcher(TrashedDirective::class),
            );
            assert($enhancedBuilder instanceof EloquentBuilder);

            $modelOrModels = $this->directiveArgValue('findOrFail', true)
                ? $enhancedBuilder->findOrFail($findValue)
                : $enhancedBuilder->find($findValue);
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw ClientSafeModelNotFoundException::fromLaravel($modelNotFoundException);
        }

        if ($modelOrModels instanceof Model) {
            return [$modelOrModels];
        }

        if ($modelOrModels === null) {
            return [];
        }

        return $modelOrModels;
    }

    public static function missingKeyToFindModel(string $find): Error
    {
        return new Error("Got no key to find a model at the expected input path: {$find}.");
    }
}
