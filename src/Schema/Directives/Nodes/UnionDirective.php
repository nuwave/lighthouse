<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class UnionDirective implements TypeResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'union';
    }

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return UnionType
     */
    public function resolveType(NodeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'resolver'
        );

        $namespace = array_get(explode('@', $resolver), '0');
        $method = array_get(explode('@', $resolver), '1', strtolower($value->getNodeName()));

        return new UnionType([
            'name' => $value->getNodeName(),
            'description' => trim(str_replace("\n", '', $value->getNode()->description)),
            'types' => function () use ($value) {
                return collect($value->getNode()->types)->map(function ($type) {
                    return schema()->instance($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => function ($value) use ($namespace, $method) {
                if ($namespace) {
                    $instance = app($namespace);

                    return call_user_func_array([$instance, $method], [$value]);
                }

                return schema()->instance(last(explode('\\', get_class($value))));
            },
        ]);
    }
}
