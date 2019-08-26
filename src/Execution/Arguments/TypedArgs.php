<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NonNull;

class TypedArgs extends \ArrayObject
{
    /**
     * @param  array  $args
     * @param  \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitions
     */
    public function __construct(array $args, array $definitions)
    {
        /** @var \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitionMap */
        $definitionMap = [];
        foreach ($definitions as $definition) {
            $definitionMap[$definition->name] = $definition;
        }

        foreach ($args as $key => $value) {
            // Since we also allow user-defined types to be registered manually, we
            // can not be sure the Lighthouse extensions are always present.
            /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions|null $extensions */
            $extensions = $definition->config['lighthouse'] ?? null;

            $definition = $definitionMap[$key];

            $type = $definition->getType();
            if($type instanceof NonNull){
                $type->getWrappedType();
            }

            if($type instanceof InputObjectType) {
                $typedChildren = new static($value, $type->getFields());

                if(isset($extensions->spread)){
                    foreach($typedChildren as $childKey => $typedChild){
                        $args[$childKey] = $typedChild;
                    }
                } else {
                    $args[$key] = $typedChildren;
                }
            }

            $typedArg = new TypedArg();

            $typedArg->value = $value;
            $typedArg->definition = $definition;

            if ($extensions) {
                $typedArg->resolver = $extensions->resolver;
            }

            $args[$key] = $typedArg;
        }

        parent::__construct($args);
    }

    public function toArray()
    {
        array_walk_recursive(
            $this,
            function(TypedArg &$typedArg){
                $typedArg = $typedArg->value;
            }
        );

        return $this;
    }
}
