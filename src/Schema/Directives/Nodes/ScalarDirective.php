<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Factories\TypeFactory;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ScalarDirective extends BaseDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @var string
     *
     * @return string
     */
    public function name()
    {
        return 'scalar';
    }

    /**
     * Resolve the node directive.
     *
     * @param NodeValue $value
     *
     * @throws DirectiveException
     * @throws \ReflectionException
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(NodeValue $value)
    {
        $scalarClass = $this->directiveArgValue('class');

        if (! $scalarClass) {
            $node = $value->getNodeName();

            throw new DirectiveException(
                "The @scalar directive must define a `class` argument assigned to $node"
            );
        }

        return TypeFactory::resolveScalarType(
            $value,
            $this->directiveArgValue('class')
        );
    }
}
