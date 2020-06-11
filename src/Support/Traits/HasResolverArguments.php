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

    public function context(): GraphQLContext
    {
        return $this->context;
    }

    /**
     * @return $this
     */
    public function setContext(GraphQLContext $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function resolveInfo(): ResolveInfo
    {
        return $this->resolveInfo;
    }

    /**
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
     * @return array<mixed>
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

    /**
     * Pass the resolver arguments to a given class if it also uses this trait.
     *
     * @param  object|iterable<object>  $receiver
     * @return object|iterable<object>
     */
    public function passResolverArguments($receiver)
    {
        if (is_iterable($receiver)) {
            foreach ($receiver as $single) {
                $this->passResolverArguments($single);
            }
        }

        return $receiver;
    }

    /**
     * @param  object  $receiver
     */
    protected function passResolverArgumentsToObject($receiver): void
    {
        if (method_exists($receiver, 'setResolverArguments')) {
            $receiver->setResolverArguments(...$this->getResolverArguments());
        }
    }
}
