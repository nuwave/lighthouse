<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\NodeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class DeleteDirective extends BaseDirective implements FieldResolver
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

                $idOrIds = reset($args);
                if ($this->directiveArgValue('globalId', false)) {
                    // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
                    if ($argumentDefinition->type->type->kind === NodeKind::LIST_TYPE) {
                        $idOrIds = array_map(
                            function ($id) {
                                return $this->globalId->decodeID($id);
                            },
                            $idOrIds
                        );
                    } else {
                        $idOrIds = $this->globalId->decodeID($idOrIds);
                    }
                }

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
