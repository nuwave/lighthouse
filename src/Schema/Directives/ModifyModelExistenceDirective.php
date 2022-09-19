<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Execution\TransactionalMutations;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

abstract class ModifyModelExistenceDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalId;

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorPool
     */
    protected $errorPool;

    /**
     * @var \Nuwave\Lighthouse\Execution\TransactionalMutations
     */
    protected $transactionalMutations;

    public function __construct(GlobalId $globalId, ErrorPool $errorPool, TransactionalMutations $transactionalMutations)
    {
        $this->globalId = $globalId;
        $this->errorPool = $errorPool;
        $this->transactionalMutations = $transactionalMutations;
    }

    public static function couldNotModify(Model $model): Error
    {
        $modelClass = get_class($model);

        return new Error("Could not modify model {$modelClass} with ID {$model->getKey()}.");
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(function ($root, array $args) {
            /** @var string|int|array<string>|array<int> $idOrIds */
            $idOrIds = reset($args);

            // TODO remove in v6
            if ($this->directiveArgValue('globalId')) {
                // @phpstan-ignore-next-line We know that global ids must be strings
                $idOrIds = $this->decodeIdOrIds($idOrIds);
            }

            $modelOrModels = $this->find(
                $this->getModelClass(),
                $idOrIds
            );

            $modifyModelExistence = function (Model $model): void {
                $success = $this->transactionalMutations->execute(
                    function () use ($model): bool {
                        return $this->modifyExistence($model);
                    },
                    $model->getConnectionName()
                );

                if (! $success) {
                    $this->errorPool->record(self::couldNotModify($model));
                }
            };

            if ($modelOrModels instanceof Model) {
                $modifyModelExistence($modelOrModels);
            } elseif ($modelOrModels instanceof Collection) {
                foreach ($modelOrModels as $model) {
                    $modifyModelExistence($model);
                }
            }

            return $modelOrModels;
        });

        return $fieldValue;
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
        $fieldNode = $this->definitionNode;
        assert($fieldNode instanceof FieldDefinitionNode);

        return $fieldNode->arguments[0]->type;
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        // Ensure there is only a single argument defined on the field.
        if (1 !== count($fieldDefinition->arguments)) {
            throw new DefinitionException(
                'The @' . $this->name() . " directive requires the field {$this->nodeName()} to only contain a single argument."
            );
        }

        if (! $this->idArgument() instanceof NonNullTypeNode) {
            throw new DefinitionException(
                'The @' . $this->name() . " directive requires the field {$this->nodeName()} to have a NonNull argument. Mark it with !"
            );
        }
    }

    /**
     * @param  string|array<string>  $idOrIds
     *
     * @return string|array<string>
     */
    protected function decodeIdOrIds($idOrIds)
    {
        // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
        if ($this->idArgument()->type instanceof ListTypeNode) {
            assert(is_array($idOrIds));

            return array_map(
                function (string $id): string {
                    return $this->globalId->decodeID($id);
                },
                $idOrIds
            );
        } else {
            assert(is_string($idOrIds));

            return $this->globalId->decodeID($idOrIds);
        }
    }

    /**
     * Find one or more models by id.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string|int|array<string>|array<int>  $idOrIds
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>|null
     */
    abstract protected function find(string $modelClass, $idOrIds);

    /**
     * Bring a model in or out of existence.
     *
     * The return value indicates if the operation was successful.
     */
    abstract protected function modifyExistence(Model $model): bool;
}
