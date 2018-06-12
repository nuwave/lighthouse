<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
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
     * @param TypeValue $value
     *
     * @return UnionType
     */
    public function resolveType(TypeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getDefinition(), self::name()),
            'resolver'
        );

        $resolverPieces = explode('@', $resolver);
        $namespace = array_get($resolverPieces, '0');
        $method = array_get($resolverPieces, '1', strtolower($value->getName()));

        return new UnionType([
            'name' => $value->getName(),
            'description' => trim(str_replace("\n", '', $value->getDefinition()->description)),
            'types' => function () use ($value) {
                return collect($value->getDefinition()->types)->map(function ($type) {
                    return graphql()->types()->get($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => function ($value) use ($namespace, $method) {
                if ($namespace) {
                    $instance = app($namespace);

                    return call_user_func_array([$instance, $method], [$value]);
                }

                return graphql()->types()->get(last(explode('\\', get_class($value))));
            },
        ]);
    }
}
