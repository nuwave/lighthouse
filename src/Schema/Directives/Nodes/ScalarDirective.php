<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ScalarDirective extends BaseDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @var string
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
        $definitionName = $this->definitionNode->name->value;
        $scalarClass = $this->directiveArgValue('class', $definitionName);

        if (!$this->isValidScalarClass($scalarClass)) {
            $scalarClass = config('lighthouse.namespaces.scalars') . '\\' . $scalarClass;
        }

        if (!$this->isValidScalarClass($scalarClass)) {
            throw new DirectiveException("Unable to find class [$scalarClass] assigned to $definitionName scalar");
        }

        return new $scalarClass;
    }

    /**
     * @param string $className
     *
     * @throws \ReflectionException
     *
     * @return bool
     */
    protected function isValidScalarClass(string $className): bool
    {
        if(! class_exists($className)){
            return false;
        }

        $reflection = new \ReflectionClass($className);

        return $reflection->isSubclassOf(ScalarType::class);
    }
}
