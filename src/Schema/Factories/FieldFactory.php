<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Types\GraphQLField;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

class FieldFactory
{
    /**
     * Convert field definition to type.
     *
     * @param Node      $fieldDefinition
     * @param TypeValue $parentType
     *
     * @return array
     */
    public static function handle(Node $fieldDefinition, TypeValue $parentType)
    {
        $fieldValue = new FieldValue($fieldDefinition, $parentType);

        $resolverDirective = $fieldValue->resolverDirective();
        $fieldValue->setResolver($resolverDirective instanceof FieldResolver
            ? $resolverDirective->resolveField($fieldValue)
            : self::defaultResolver($fieldValue)
        );

        $fieldValue->setResolver(self::applyMiddleware($fieldValue));

        $attributes = [
            'type' => $fieldValue->getReturnTypeInstance(),
            'description' => $fieldValue->getDescription(),
        ];

        $args = self::getArgs($fieldValue);

        if ($args->isNotEmpty()) {
            $attributes['args'] = $args->toArray();
        }

        if ($resolve = $fieldValue->getResolver()) {
            $attributes['resolve'] = $fieldValue->wrap($resolve);
        }

        if ($complexity = $fieldValue->getComplexity()) {
            $attributes['complexity'] = $complexity;
        }

        return GraphQLField::toArray($attributes);
    }

    /**
     * Get default field resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected static function defaultResolver(FieldValue $value)
    {
        switch ($value->getParentTypeName()) {
            case 'Mutation':
                return self::mutationResolver($value);
            case 'Query':
                return self::queryResolver($value);
            default:
                // Use graphql-php default resolver
                return \Closure::fromCallable([\GraphQL\Executor\Executor::class, 'defaultFieldResolver']);
        }
    }

    /**
     * Get default mutation resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected static function mutationResolver(FieldValue $value)
    {
        return function ($obj, array $args, $context = null, $info = null) use ($value) {
            $class = config('lighthouse.namespaces.mutations').'\\'.studly_case($value->getFieldName());

            return (new $class($obj, $args, $context, $info))->resolve();
        };
    }

    /**
     * Get default query resolver.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected static function queryResolver(FieldValue $value)
    {
        return function ($obj, array $args, $context = null, $info = null) use ($value) {
            $class = config('lighthouse.namespaces.queries').'\\'.studly_case($value->getFieldName());

            return (new $class($obj, $args, $context, $info))->resolve();
        };
    }

    /**
     * Get collection of field arguments.
     *
     * @param FieldValue $value
     *
     * @return \Illuminate\Support\Collection
     */
    protected static function getArgs(FieldValue $value)
    {
        return collect(data_get($value->getFieldDefinition(), 'arguments', []))
            ->mapWithKeys(function (InputValueDefinitionNode $arg) use ($value) {
                $argValue = new ArgumentValue($value, $arg);

                return [$argValue->getArgName() => app(ArgumentFactory::class)->handle($argValue)];
            });
    }

    /**
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected static function applyMiddleware(FieldValue $value)
    {
        return $value->middlewareDirectives()
            ->reduce(function (FieldValue $value, FieldMiddleware $middleware) {
                return $middleware->handleField($value);
            }, $value)
            ->getResolver();
    }
}
