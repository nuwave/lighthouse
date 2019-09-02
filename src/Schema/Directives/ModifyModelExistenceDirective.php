<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\ListTypeNode;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Language\AST\NonNullTypeNode;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

abstract class ModifyModelExistenceDirective extends BaseDirective implements FieldResolver
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

                $argumentType = $argumentDefinition->type;
                if (! $argumentType instanceof NonNullTypeNode) {
                    throw new DirectiveException(
                        'The @'.static::name()." directive requires the field {$this->definitionNode->name->value} to have a NonNull argument. Mark it with !"
                    );
                }

                /** @var string|int|string[]|int[] $idOrIds */
                $idOrIds = reset($args);

                if ($this->directiveArgValue('globalId', false)) {
                    // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
                    if ($argumentType->type instanceof ListTypeNode) {
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

                $modelOrModels = $this->find(
                    $this->getModelClass(),
                    $idOrIds
                );

                if (! $modelOrModels) {
                    return;
                }

                if ($modelOrModels instanceof Model) {
                    $this->modifyExistence($modelOrModels);
                }

                if ($modelOrModels instanceof Collection) {
                    foreach ($modelOrModels as $model) {
                        $this->modifyExistence($model);
                    }
                }

                return $modelOrModels;
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
                'The @'.static::name()." directive requires the field {$this->definitionNode->name->value} to only contain a single argument."
            );
        }

        return $this->definitionNode->arguments[0];
    }

    /**
     * Find one or more models by id.
     *
     * @param  string|\Illuminate\Database\Eloquent\Model  $modelClass
     * @param  string|int|string[]|int[]  $idOrIds
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    abstract protected function find(string $modelClass, $idOrIds);

    /**
     * Bring a model in or out of existence.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    abstract protected function modifyExistence(Model $model): void;
}
