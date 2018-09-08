<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class UnionDirective extends BaseDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'union';
    }

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @return Type
     * @throws DirectiveException
     */
    public function resolveNode(NodeValue $value)
    {
        return new UnionType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'types' => function () use ($value) {
                return collect($value->getNode()->types)->map(function ($type) {
                    return resolve(TypeRegistry::class)->get($type->name->value);
                })->filter()->toArray();
            },
            'resolveType' => $this->getResolver(function ($value, $context, ResolveInfo $info) {
                $unionName = $this->definitionNode->name->value;

                // Try to locate a fallback resolver corresponds to the config file.
                $resolverClass = config('lighthouse.namespaces.unions') . '\\' . $unionName;

                if ( \method_exists($resolverClass, 'resolve')) {
                    $resolver = resolve($resolverClass);
                    return $resolver->resolve($value, $context, $info);
                }

                return resolve(TypeRegistry::class)->get(
                    str_after('\\', \get_class($value))
                );
            })
        ]);
    }
}
