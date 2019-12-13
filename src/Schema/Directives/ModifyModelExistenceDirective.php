<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

abstract class ModifyModelExistenceDirective extends BaseDirective implements FieldResolver, FieldManipulator, DefinedDirective
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
                /** @var string|int|string[]|int[] $idOrIds */
                $idOrIds = reset($args);

                if ($this->directiveArgValue('globalId', false)) {
                    // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
                    if ($this->idArgument()->type instanceof ListTypeNode) {
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
     * Get the type of the id argument.
     *
     * Not using an actual type hint, as the manipulateFieldDefinition function
     * validates the type during schema build time.
     *
     * @return \GraphQL\Language\AST\NonNullTypeNode
     */
    protected function idArgument()
    {
        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldNode */
        $fieldNode = $this->definitionNode;

        return $fieldNode->arguments[0]->type;
    }

    /**
     * @param  DocumentAST  $documentAST
     * @param  FieldDefinitionNode  $fieldDefinition
     * @param  ObjectTypeDefinitionNode  $parentType
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        // Ensure there is only a single argument defined on the field.
        if (count($this->definitionNode->arguments) !== 1) {
            throw new DefinitionException(
                'The @'.static::name()." directive requires the field {$this->nodeName()} to only contain a single argument."
            );
        }

        if (! $this->idArgument() instanceof NonNullTypeNode) {
            throw new DefinitionException(
                'The @'.static::name()." directive requires the field {$this->nodeName()} to have a NonNull argument. Mark it with !"
            );
        }
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
