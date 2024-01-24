<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class DeleteDirective extends ModifyModelExistenceDirective implements ArgResolver, ArgManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Delete one or more models.
"""
directive @delete(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function enhanceBuilder(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder;
    }

    protected function modifyExistence(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Delete one or more related models.
     *
     * @param  Model  $parent
     * @param  mixed|array<mixed>  $idOrIds
     */
    public function __invoke($parent, $idOrIds): void
    {
        $relationName = $this->directiveArgValue(
            'relation',
            // Use the name of the argument if no explicit relation name is given
            $this->nodeName(),
        );
        $relation = $parent->{$relationName}();
        assert($relation instanceof Relation);

        // Those types of relations may only have one related model attached to
        // it, so we don't need to use an ID to know which model to delete.
        $relationIsHasOneLike = $relation instanceof HasOne || $relation instanceof MorphOne;
        // This includes MorphTo, which is a subclass of BelongsTo
        $relationIsBelongsToLike = $relation instanceof BelongsTo;

        if ($relationIsHasOneLike || $relationIsBelongsToLike) {
            assert($relation instanceof HasOne || $relation instanceof MorphOne || $relation instanceof BelongsTo);
            // Only delete if the given value is truthy, since
            // the client might use a variable and always pass the argument.
            // Deleting when `false` is given seems wrong.
            if ($idOrIds) {
                if ($relationIsBelongsToLike) {
                    $relation->dissociate();
                    $relation->getParent()->save();
                }

                $relation->delete();
            }
        } else {
            // @phpstan-ignore-next-line Relation&Builder mixin not recognized
            $related = $relation->make();
            assert($related instanceof Model);
            $related::destroy($idOrIds);
        }
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        if (! $this->directiveArgValue('relation')) {
            throw new DefinitionException('The @delete directive requires "relation" to be set when used as an argument resolver.');
        }
    }
}
