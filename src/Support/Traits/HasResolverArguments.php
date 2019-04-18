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
     * @var mixed[]
     */
    protected $args;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    protected $context;

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
     * @return mixed[]
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * @param  mixed[]  $args
     * @return $this
     */
    public function setArgs(array $args): self
    {
        $this->args = $args;

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
     * Set all resolver arguments at once.
     *
     * @param  mixed|null  $root
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return $this
     */
    public function setResolverArguments($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): self
    {
        return $this
            ->setRoot($root)
            ->setArgs($args)
            ->setContext($context)
            ->setResolveInfo($resolveInfo);
    }

    /**
     * Get all the resolver arguments.
     *
     * @return mixed[]
     */
    public function getResolverArguments(): array
    {
        return [
            $this->root(),
            $this->args(),
            $this->context(),
            $this->resolveInfo(),
        ];
    }
}
