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

class RestoreDirective extends BaseDirective implements FieldResolver
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
        return 'restore';
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
                        "The @restore directive requires the field {$this->definitionNode->name->value} to have a NonNull argument. Mark it with !"
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
                $model = $modelClass::withTrashed()->find($idOrIds);

                if (! $model) {
                    return;
                }

                if ($model instanceof Model) {
                    $model->restore();
                }

                if ($model instanceof Collection) {
                    foreach ($model as $modelItem) {
                        $modelItem->restore();
                    }
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
                "The @restore directive requires the field {$this->definitionNode->name->value} to only contain a single argument."
            );
        }

        return $this->definitionNode->arguments[0];
    }
}
