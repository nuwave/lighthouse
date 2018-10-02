<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\NodeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class DeleteDirective extends BaseDirective implements FieldResolver
{
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
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        return $value->setResolver(function ($root, array $args) {
            $argumentDefinition = $this->getSingleArgumentDefinition();

            if(NodeKind::NON_NULL_TYPE !== $argumentDefinition->type->kind){
                throw new DirectiveException(
                    "The @delete directive requires the field {$this->definitionNode->name->value} to have a NonNull argument. Mark it with !"
                );
            }

            $idOrIds = reset($args);
            if($this->directiveArgValue('globalId', false)){
                // At this point we know the type is at least wrapped in a NonNull type, so we go one deeper
                if(NodeKind::LIST_TYPE === $argumentDefinition->type->type->kind){
                    $idOrIds = array_map([GlobalId::class, 'decodeId'], $idOrIds);
                } else {
                    $idOrIds = GlobalId::decodeId($idOrIds);
                }
            }

            $modelClass = $this->getModelClass();
            $model = $modelClass::find($idOrIds);

            if (!$model) {
                return null;
            }

            if($model instanceof Model){
                $model->delete();
            }

            if($model instanceof Collection){
                $modelClass::destroy($idOrIds);
            }

            return $model;
        });
    }

    protected function getSingleArgumentDefinition(): InputValueDefinitionNode
    {
        if (1 !== count($this->definitionNode->arguments)) {
            throw new DirectiveException(
                "The @delete directive requires the field {$this->definitionNode->name->value} to only contain a single argument."
            );
        }

        return $this->definitionNode->arguments[0];
    }
}
