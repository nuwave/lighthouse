<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Serializer implements ContextSerializer
{
    use SerializesAndRestoresModelIdentifiers;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    public function __construct(CreatesContext $createsContext)
    {
        $this->createsContext = $createsContext;
    }

    /**
     * Serialize the context.
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
            'user' => $this->getSerializedPropertyValue($context->user()),
        ]);
    }

    /**
     * Unserialize the context.
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
                $user = $this->getRestoredPropertyValue($rawUser);

                // This is here for backwards compatibility, before the Laravel
                // `SerializesAndRestoresModelIdentifiers` trait was used to
                // serialize the user data it was `serialize`d separately
                // This allows existing subscriptions to be seamlessly
                // upgraded without breaking an active subscription
                // TODO remove this fallback in v5
                if (is_string($user)) {
                    return unserialize($rawUser);
                }

                return $user;
            }
        );

        return $this->createsContext->generate($request);
    }
}
