<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class InterfaceDirective implements TypeResolver
{
    use HandlesDirectives, HandlesTypes;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'interface';
    }

    /**
     * Resolve the node directive.
     *
     * @param TypeValue $value
     *
     * @return Type
     */
    public function resolveType(TypeValue $value)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'resolver'
        );

        $instance = app(array_get(explode('@', $resolver), '0'));
        $method = array_get(explode('@', $resolver), '1');

        return new InterfaceType([
            'name' => $value->getNodeName(),
            'description' => trim(str_replace("\n", '', $value->getNode()->description)),
            'fields' => function () use ($value) {
                return $this->getFields($value);
            },
            'resolveType' => function ($value) use ($instance, $method) {
                return call_user_func_array([$instance, $method], [$value]);
            },
        ]);
    }
}
