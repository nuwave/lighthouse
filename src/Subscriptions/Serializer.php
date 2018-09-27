<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Serializer implements ContextSerializer
{
    /**
     * Serialize the context.
     *
     * @param mixed $context
     *
     * @return string
     */
    public function serialize($context)
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
        $user = null;
        $request = null;
        $data = json_decode($context, true);

        if ($user = array_get($data, 'user')) {
            $user = unserialize($user);
        }

        if ($request = array_get($data, 'request')) {
            $request = $this->unserializeRequest($request);
        }

        return new Context($request, $user);
    }

    /**
     * Serialize the request object.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function serializeRequest(Request $request)
    {
        return json_encode([
            'query' => $request->query->all(),
            'request' => $request->request->all(),
            'attributes' => $request->attributes->all(),
            'cookies' => [],
            'files' => [],
            'server' => array_except($request->server->all(), ['HTTP_AUTHORIZATION']),
            'content' => $request->getContent(),
        ]);
    }

    /**
     * Unserialize the request object.
     *
     * @param string $request
     *
     * @return Request
     */
    protected function unserializeRequest($request): Request
    {
        $data = json_decode($request, true);

        return new Request(
            array_get($data, 'query'),
            array_get($data, 'request'),
            array_get($data, 'attributes'),
            array_get($data, 'cookies'),
            array_get($data, 'files'),
            array_get($data, 'server'),
            array_get($data, 'content')
        );
    }
}
