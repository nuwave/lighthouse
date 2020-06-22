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

    public function __construct(GlobalId $globalId)
    {
        $this->globalId = $globalId;
    }

    /**
     * Resolve the field directive.
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args) {
                /** @var string|int|array<string>|array<int> $idOrIds */
                $idOrIds = reset($args);

                if ($this->directiveArgValue('globalId', false)) {
                    // @phpstan-ignore-next-line We know that global ids must be strings
                    $idOrIds = $this->decodeIdOrIds($idOrIds);
                }

                $modelOrModels = $this->find(
                    $this->getModelClass(),
                    $idOrIds
                );

                if ($modelOrModels === null) {
                    return;
                }

                if ($modelOrModels instanceof Model) {
                    $this->modifyExistence($modelOrModels);
                } elseif ($modelOrModels instanceof Collection) {
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
     * @return mixed but should be a \GraphQL\Language\AST\NonNullTypeNode
     */
    protected function idArgument()
    {
        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldNode */
        $fieldNode = $this->definitionNode;

        return $fieldNode->arguments[0]->type;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        // Ensure there is only a single argument defined on the field.
        if (count($fieldDefinition->arguments) !== 1) {
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
     * @param  string|array<string>  $idOrIds
     * @return string|array<string>
     */
    protected function decodeIdOrIds($idOrIds)
    {
        // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
        if ($this->idArgument()->type instanceof ListTypeNode) {
            /** @var array<string> $idOrIds */
            return array_map(
                function (string $id): string {
                    return $this->globalId->decodeID($id);
                },
                $idOrIds
            );
        } else {
            /** @var string $idOrIds */
            return $this->globalId->decodeID($idOrIds);
        }
    }

    /**
     * Find one or more models by id.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string|int|string[]|int[]  $idOrIds
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>|null
     */
    abstract protected function find(string $modelClass, $idOrIds);

    /**
     * Bring a model in or out of existence.
     */
    abstract protected function modifyExistence(Model $model): void;
}
