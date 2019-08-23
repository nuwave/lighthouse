<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class TypedArgs extends \ArrayObject
{
    /**
     * @param  array  $args
     * @param  \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitions
     */
    public function __construct(array $args, array $definitions)
    {
        $definitionMap = [];
        foreach ($definitions as $definition) {
            $definitionMap[$definition->name] = $definition;
        }

        foreach ($args as $key => $value) {
            $typedArg = new TypedArg();

            $typedArg->value = $value;

            $definition = $definitionMap[$key];
            $typedArg->definition = $definition;

            if ($config = $definition->config['lighthouse'] ?? false) {
                if ($resolver = $config->resolver) {
                    $typedArg->resolver = $resolver;
                }
            }

            $args[$key] = $typedArg;
        }

        parent::__construct($args);
    }
}
