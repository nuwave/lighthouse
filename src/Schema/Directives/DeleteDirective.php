<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\NodeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class DeleteDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    /**
     * The GlobalId resolver.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalId;

    /**
     * DeleteDirective constructor.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\GlobalId  $globalId
     * @return void
     */
    public function __construct(GlobalId $globalId)
    {
        $this->globalId = $globalId;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'delete';
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Delete one or more models by their ID.
The field must have an single non-null argument that may be a list.
"""
directive @delete(
  """
  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args) {
                $argumentDefinition = $this->getSingleArgumentDefinition();

                if ($argumentDefinition->type->kind !== NodeKind::NON_NULL_TYPE) {
                    throw new DirectiveException(
                        "The @delete directive requires the field {$this->definitionNode->name->value} to have a NonNull argument. Mark it with !"
                    );
                }

                /** @var string|int|string[] $idOrIds */
                $idOrIds = reset($args);
                if ($this->directiveArgValue('globalId', false)) {
                    // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
                    if ($argumentDefinition->type->type->kind === NodeKind::LIST_TYPE) {
                        $idOrIds = array_map(
                            function (string $id): string {
                                return $this->globalId->decodeID($id);
                            },
                            $idOrIds
                        );
                    } else {
                        $idOrIds = $this->globalId->decodeID($idOrIds);
                    }
                }

                /** @var \Illuminate\Database\Eloquent\Model $modelClass */
                $modelClass = $this->getModelClass();
                $model = $modelClass::find($idOrIds);

                if (! $model) {
                    return;
                }

                if ($model instanceof Model) {
                    $model->delete();
                }

                if ($model instanceof Collection) {
                    $modelClass::destroy($idOrIds);
                }

                return $model;
            }
        );
    }

    /**
     * Ensure there is only a single argument defined on the field.
     *
     * @return \GraphQL\Language\AST\InputValueDefinitionNode
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function getSingleArgumentDefinition(): InputValueDefinitionNode
    {
        if (count($this->definitionNode->arguments) !== 1) {
            throw new DirectiveException(
                "The @delete directive requires the field {$this->definitionNode->name->value} to only contain a single argument."
            );
        }

        return $this->definitionNode->arguments[0];
    }
}
