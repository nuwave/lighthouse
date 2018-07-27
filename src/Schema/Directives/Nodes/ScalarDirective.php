<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
     * @return \GraphQL\Type\Definition\Type
     * @throws DirectiveException
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

    protected function isValidScalarClass(string $className): bool
    {
        if(! class_exists($className)){
            return false;
        }

        $reflection = new \ReflectionClass($className);

        return $reflection->isSubclassOf(ScalarType::class);
    }
}
