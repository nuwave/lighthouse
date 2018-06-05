<?php

namespace Nuwave\Lighthouse\Schema\Factories;


use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class NodeFactory
{
    use HandlesDirectives, HandlesTypes;

    /**
     * Transform node to type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    public function handle(NodeValue $value)
    {
        $value = $value->getNode()->hasResolver()
            ? $value->getNode()->resolver()->resolve($value)
            : $this->transform($value);

        return $this->applyMiddleware($this->attachInterfaces($value))
            ->getType();
    }

    /**
     * Check if node has a resolver directive.
     *
     * @param NodeValue $value
     *
     * @return bool
     */
    protected function hasResolver(NodeValue $value)
    {
        return directives()->hasNodeResolver($value->getNode());
    }

    /**
     * Use directive resolver to transform type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function useResolver(NodeValue $value)
    {
        return directives()->forNode($value->getNode())
            ->resolveNode($value);
    }

    /**
     * Transform value to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function transform(NodeValue $value) : NodeValue
    {
        $value->setType($value->getNode()->toType());
        return $value;
    }

    /**
     * Resolve enum definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    public function enum(NodeValue $value)
    {
        $enum = new EnumType([
            'name' => $value->getNodeName(),
            'values' => collect($value->getNode()->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    $directive = $this->fieldDirective($field, 'enum');

                    if (! $directive) {
                        return [];
                    }

                    return [$field->name->value => [
                        'value' => $this->directiveArgValue($directive, 'value'),
                        'description' => $this->safeDescription($field->description),
                    ]];
                })->toArray(),
        ]);

        return $value->setType($enum);
    }

    /**
     * Resolve scalar definition to type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\ScalarType
     */
    public function scalar(NodeValue $value)
    {
        return ScalarResolver::resolve($value);
    }

    /**
     * Resolve object type definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function objectType(NodeValue $value)
    {
        $objectType = \Nuwave\Lighthouse\Support\Webonyx\Type::toType(new ObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
        ]));

        return $value->setType($objectType);
    }

    /**
     * Resolve input type definition to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function inputObjectType(NodeValue $value)
    {
        $inputType = new InputObjectType([
            'name' => $value->getNodeName(),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
        ]);

        return $value->setType($inputType);
    }

    /**
     * Resolve client directive.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function clientDirective(NodeValue $value)
    {
        $node = $value->getNode();
        $args = $node->arguments
            ? collect($node->arguments)->map(function ($input) {
                return new FieldArgument([
                    'name' => data_get($input, 'name.value'),
                    'defaultValue' => data_get($input, 'defaultValue.value'),
                    'description' => data_get($input, 'description'),
                    'type' => NodeResolver::resolve(data_get($input, 'type')),
                ]);
            })->toArray()
            : null;

        $directive = new Directive([
            'name' => $node->name->value,
            'locations' => collect($node->locations)->map(function ($location) {
                return $location->value;
            })->toArray(),
            'args' => $args,
            'astNode' => $node,
        ]);

        return $value->setType($directive);
    }

    /**
     * Extend type definition.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function extend(NodeValue $value)
    {
        $value->setNode(
            $value->getNode()->definition
        );

        $type = $value->getType();
        $originalFields = value($type->config()->fields());
        $type->config()->fields(function () use ($originalFields, $value) {
            return array_merge($originalFields, $this->getFields($value));
        });

        return $value;
    }

    /**
     * Attach interfaces to type.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function attachInterfaces(NodeValue $value)
    {
        $type = $value->getType();
        $type->config['interfaces'] = function () use ($value) {
            return collect($value->getInterfaces())->map(function ($interface) {
                return schema()->instance($interface);
            })->filter()->toArray();
        };

        return $value;
    }

    /**
     * Apply node middleware.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function applyMiddleware(NodeValue $value)
    {
        return app(Pipeline::class)
            ->send($value)
            ->through($value->getNode()->middlewares())
            ->via('handle')
            ->then(function(NodeValue $value) {
                return $value;
            });
    }
}
