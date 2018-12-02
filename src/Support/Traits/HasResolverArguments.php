<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Type\Definition\ResolveInfo;

trait HasResolverArguments
{
    /**
     * @var mixed
     */
    protected $root;

    /**
     * @var mixed
     */
    protected $context;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var ResolveInfo
     */
    protected $resolveInfo;

    /**
     * @return mixed
     */
    public function root()
    {
        return $this->root;
    }

    /**
     * @param mixed $root
     *
     * @return static
     */
    public function setRoot($root)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * @return mixed
     */
    public function context()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     *
     * @return static
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return array
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     *
     * @return static
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @return ResolveInfo
     */
    public function resolveInfo(): ResolveInfo
    {
        return $this->resolveInfo;
    }

    /**
     * @param ResolveInfo $resolveInfo
     *
     * @return static
     */
    public function setResolveInfo(ResolveInfo $resolveInfo)
    {
        $this->resolveInfo = $resolveInfo;

        return $this;
    }

    /**
     * @param mixed|null  $root
     * @param array       $args
     * @param null        $context
     * @param ResolveInfo $resolveInfo
     *
     * @return static
     */
    public function setResolverArguments($root, array $args, $context = null, ResolveInfo $resolveInfo)
    {
        return $this->setRoot($root)
                    ->setArguments($args)
                    ->setContext($context)
                    ->setResolveInfo($resolveInfo);
    }

    /**
     * Get all the resolver arguments.
     *
     * @return array
     */
    public function getResolverArguments(): array
    {
        return [
            $this->root(),
            $this->arguments(),
            $this->context(),
            $this->resolveInfo(),
        ];
    }
}
