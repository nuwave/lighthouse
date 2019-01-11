<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait HasResolverArguments
{
    /**
     * @var mixed|null
     */
    protected $root;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    protected $context;

    /**
     * @var mixed[]
     */
    protected $arguments;

    /**
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $resolveInfo;

    /**
     * @return mixed|null
     */
    public function root()
    {
        return $this->root;
    }

    /**
     * @param  mixed  $root
     * @return $this
     */
    public function setRoot($root): self
    {
        $this->root = $root;

        return $this;
    }

    /**
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function context(): GraphQLContext
    {
        return $this->context;
    }

    /**
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @return $this
     */
    public function setContext(GraphQLContext $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param  mixed[]  $arguments
     * @return $this
     */
    public function setArguments(array $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @return \GraphQL\Type\Definition\ResolveInfo
     */
    public function resolveInfo(): ResolveInfo
    {
        return $this->resolveInfo;
    }

    /**
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return $this
     */
    public function setResolveInfo(ResolveInfo $resolveInfo): self
    {
        $this->resolveInfo = $resolveInfo;

        return $this;
    }

    /**
     * @param  mixed|null  $root
     * @param  array  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return $this
     */
    public function setResolverArguments($root, array $args, $context, ResolveInfo $resolveInfo): self
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
