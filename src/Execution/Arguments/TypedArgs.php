<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InputObjectType;

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
        foreach ($definitions as $definition) {
            $instance->definitions[$definition->name] = $definition;
        }

        return $instance;
    }

    public function offsetGet($name)
    {
        $value = parent::offsetGet($name);

        $argType = $this->definitions[$name]->getType();
        if ($argType instanceof InputObjectType) {
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

    /**
     * @param  string  $offset
     * @return \GraphQL\Type\Definition\FieldArgument|\GraphQL\Type\Definition\InputObjectField|null
     */
    public function definition(string $offset)
    {
        if (! isset($this->definitions[$offset])) {
            return;
        }

        return $this->definitions[$offset];
    }

    public function type(string $offset): ?InputType
    {
        $definition = $this->definition($offset);

        return $definition
            ? $definition->getType()
            : null;
    }

    public function iteratorWithDefinition()
    {
        foreach (parent::getIterator() as $key => $_) {
            $typedArg = new TypedArg();
            $typedArg->value = $this->offsetGet($key);
            $typedArg->definition = $this->definition($key);

            yield $key => $typedArg;
        }
    }

    public function partitionResolverInputs(): array
    {
        $before = [];
        $regular = [];
        $after = [];

        foreach ($this->getIterator() as $name => $value) {
            $argDef = $this->definitions[$name];

            if (! isset($argDef->config['lighthouse'])) {
                $regular[$name] = $value;
                continue;
            }

            /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $config */
            $config = $argDef->config['lighthouse'];

            if ($config->resolveBefore instanceof ResolveNestedBefore) {
                $before[$name] = $value;
            } elseif ($config->resolveBefore instanceof ResolveNestedAfter) {
                $after[$name] = $value;
            } else {
                $regular[$name] = $value;
            }
        }

        return [
            $before,
            $regular,
            $after,
        ];
    }
}
