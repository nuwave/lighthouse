<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Generator;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;

class TypedArgs extends \ArrayObject
{
    /** @var \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitions */
    protected $definitions;

    /**
     * @param  array  $input
     * @param  \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitions
     */
    public static function fromArgs(array $input, array $definitions)
    {
        $instance = new self($input);
        foreach($definitions as $definition) {
            $instance->definitions[$definition->name] = $definition;
        }

        return $instance;
    }

    public function offsetGet($name)
    {
        $value = parent::offsetGet($name);

        $argType = $this->definitions[$name]->getType();
        if($argType instanceof InputObjectType) {
            $value = self::fromArgs($value, $argType->getFields());
        }

        return $value;
    }

    public function getIterator()
    {
        foreach (parent::getIterator() as $key => $_) {
            yield $key => $this->offsetGet($key);
        }
    }

    public function type(string $offset): ?InputType
    {
        if(! isset($this->definitions[$offset])){
            return null;
        }

        return $this->definitions[$offset]->getType();
    }

    public function partitionResolverInputs(): array
    {
        $before = [];
        $regular = [];
        $after = [];

        foreach($this->getIterator() as $name => $value){
            $argDef = $this->definitions[$name];

            if(! isset($argDef->config['lighthouse'])){
                $regular[$name] = $value;
                continue;
            }

            /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $config */
            $config = $argDef->config['lighthouse'];

            if($config->resolver instanceof ResolveNestedBefore){
                $before[$name] = $value;
            } elseif ($config->resolver instanceof ResolveNestedAfter){
                $after[$name] = $value;
            } else {
                $regular[$name] = $value;
            }
        }

        return [
            $before,
            $regular,
            $after
        ];
    }
}
