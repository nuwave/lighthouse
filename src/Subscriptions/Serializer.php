<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
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
     * @param mixed $context
     *
     * @return string
     */
    public function serialize($context): string
    {
        $user = null;
        $request = null;

        if ($user = data_get($context, 'user')) {
            $user = serialize($user);
        }

        if ($request = data_get($context, 'request')) {
            $request = $this->serializeRequest($request);
        }

        return json_encode(compact('user', 'request'));
    }

    /**
     * Unserialize the context.
     *
     * @param string $context
     *
     * @return mixed
     */
    public function unserialize($context)
    {
        $data = json_decode($context, true);

        if (! $serializedRequest = array_get($data, 'request')) {
            return null;
        }

        return $this->createsContext->generate(
            $this->unserializeRequest($serializedRequest)
        );
    }

    /**
     * Serialize the request object.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function serializeRequest(Request $request): string
    {
        return json_encode([
            'query' => $request->query->all(),
            'request' => $request->request->all(),
            'attributes' => $request->attributes->all(),
            'cookies' => [],
            'files' => [],
            'server' => array_except($request->server->all(), ['HTTP_AUTHORIZATION']),
            'content' => $request->getContent(),
            'user' => serialize($request->user()),
        ]);
    }

    /**
     * Unserialize the request object.
     *
     * @param string $request
     *
     * @return Request
     */
    protected function unserializeRequest(string $request): Request
    {
        $data = json_decode($request, true);
        $request = new Request(
            array_get($data, 'query'),
            array_get($data, 'request'),
            array_get($data, 'attributes'),
            array_get($data, 'cookies'),
            array_get($data, 'files'),
            array_get($data, 'server'),
            array_get($data, 'content')
        );

        $request->setUserResolver(function () use ($data) {
            $user = array_get($data, 'user');

            return ! empty($user) ? unserialize($user) : null;
        });

        return $request;
    }
}
