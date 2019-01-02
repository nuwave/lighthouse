<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Serializer implements ContextSerializer
{
    /**
     * @var CreatesContext
     */
    protected $createsContext;

    /**
     * @param CreatesContext $createsContext
     */
    public function __construct(CreatesContext $createsContext)
    {
        $this->createsContext = $createsContext;
    }

    /**
     * Serialize the context.
     *
     * @param GraphQLContext $context
     *
     * @return string
     */
    public function serialize(GraphQLContext $context): string
    {
        $request = $context->request();

        return serialize([
            'request' => [
                'query' => $request->query->all(),
                'request' => $request->request->all(),
                'attributes' => $request->attributes->all(),
                'cookies' => [],
                'files' => [],
                'server' => array_except($request->server->all(), ['HTTP_AUTHORIZATION']),
                'content' => $request->getContent(),
            ],
            'user' => serialize($context->user()),
        ]);
    }

    /**
     * Unserialize the context.
     *
     * @param string $context
     *
     * @return GraphQLContext
     */
    public function unserialize(string $context): GraphQLContext
    {
        $context = unserialize($context);

        $request = new Request(
            $context['query'],
            $context['request'],
            $context['attributes'],
            $context['cookies'],
            $context['files'],
            $context['server'],
            $context['content']
        );

        $request->setUserResolver(
            function () use ($context) {
                return unserialize($context['user']);
            }
        );

        return $this->createsContext->generate($request);
    }
}
