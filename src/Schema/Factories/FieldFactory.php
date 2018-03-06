<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class FieldFactory
{
    /**
     * Convert field definition to type.
     *
     * @param FieldValue $value
     *
     * @return array
     */
    public function handle(FieldValue $value)
    {
        $value->setType(NodeResolver::resolve($value->getField()->type));

        $this->hasResolver($value)
            ? $this->useResolver($value)
            : $value->setResolver($this->resolver($value));

        $field = [
            'type' => $value->getType(),
            'description' => $value->getDescription(),
            'resolve' => directives()->fieldMiddleware($value->getField())
                ->reduce(function ($value, $middleware) {
                    return $middleware->handle($value);
                }, $value)->getResolver(),
        ];

        $args = $this->getArgs($value);
        if (! $args->isEmpty()) {
            $field['args'] = $args->toArray();
        }

        return $field;
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param FieldValue $value
     *
     * @return bool
     */
    protected function hasResolver(FieldValue $value)
    {
        return directives()->hasResolver($value->getField());
    }

    /**
     * Use directive resolver to transform field.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    protected function useResolver(FieldValue $value)
    {
        return directives()->fieldResolver($value->getField())
            ->handle($value);
    }

    /**
     * Get default field resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function resolver(FieldValue $value)
    {
        switch ($value->getNodeName()) {
            case 'Mutation':
                return $this->mutationResolver($value);
            case 'Query':
                return $this->queryResolver($value);
            default:
                return $this->defaultResolver($value);
        }
    }

    /**
     * Default field resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function defaultResolver(FieldValue $value)
    {
        $name = $value->getFieldName();

        return function ($parent, array $args) use ($name) {
            return data_get($parent, $name);
        };
    }

    /**
     * Get default mutation resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function mutationResolver(FieldValue $value)
    {
        $class = config('lighthouse.namespaces.mutations').'\\'.studly_case($value->getFieldName());

        $mutation = app($class);

        return (new \ReflectionClass($mutation))->getMethod('resolve')->getClosure($mutation);
    }

    /**
     * Get default query resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function queryResolver(FieldValue $value)
    {
        $class = config('lighthouse.namespaces.queries').'\\'.studly_case($value->getFieldName());

        $query = app($class);

        return (new \ReflectionClass($query))->getMethod('resolve')->getClosure($query);
    }

    /**
     * Get collection of field arguments.
     *
     * @param FieldValue $value
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getArgs(FieldValue $value)
    {
        $factory = $this->argFactory();

        return collect(data_get($value->getField(), 'arguments', []))
            ->mapWithKeys(function (InputValueDefinitionNode $arg) use ($factory, $value) {
                $argValue = new ArgumentValue($value->getField(), $arg);

                return [$argValue->getArgName() => $factory->handle($argValue)];
            });
    }

    /**
     * Get instance of argument factory.
     *
     * @return ArgumentFactory
     */
    protected function argFactory()
    {
        return app(ArgumentFactory::class);
    }
}
