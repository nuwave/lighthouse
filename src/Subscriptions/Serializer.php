<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Serializer implements ContextSerializer
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    /**
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @return void
     */
    public function __construct(CreatesContext $createsContext)
    {
        $this->createsContext = $createsContext;
    }

    /**
     * Serialize the context.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
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
                'server' => Arr::except($request->server->all(), ['HTTP_AUTHORIZATION']),
                'content' => $request->getContent(),
            ],
            'user' => serialize($context->user()),
        ]);
    }

    /**
     * Unserialize the context.
     *
     * @param  string  $context
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function unserialize(string $context): GraphQLContext
    {
        [
            'request' => $rawRequest,
            'user' => $rawUser
        ] = unserialize($context);

        $request = new Request(
            $rawRequest['query'],
            $rawRequest['request'],
            $rawRequest['attributes'],
            $rawRequest['cookies'],
            $rawRequest['files'],
            $rawRequest['server'],
            $rawRequest['content']
        );

        $request->setUserResolver(
            function () use ($rawUser) {
                return unserialize($rawUser);
            }
        );

        return $this->createsContext->generate($request);
    }
}
