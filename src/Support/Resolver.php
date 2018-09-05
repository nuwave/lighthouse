<?php

namespace Nuwave\Lighthouse\Support;

class Resolver
{
    /**
     * The class name of the resolver.
     * @var string
     */
    private $className;

    /**
     * The method name of the resolver's class.
     * @var string
     */
    private $methodName;

    /**
     * Resolver constructor.
     */
    public function __construct(string $className, string $methodName)
    {
        if ( ! method_exists($className, $methodName)) {
            throw new \Exception("Method '{$resolverMethod}' does not exist on class '{$resolverClass}'");
        }

        $this->className = $className;
        $this->methodName = $methodName;
    }

    /**
     * @return string
     */
    public function className(): string
    {
        return $this->className;
    }

    public function methodName(): string
    {
        return $this->methodName;
    }
}