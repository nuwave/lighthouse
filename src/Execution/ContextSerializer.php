<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Http\Request;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

class ContextSerializer implements SerializesContext
{
    use SerializesAndRestoresModelIdentifiers;

    public function __construct(
        protected CreatesContext $createsContext,
    ) {}

    public function serialize(GraphQLContext $context): string
    {
        $request = $context->request();

        return serialize([
            'request' => $request
                ? [
                    'query' => $request->query->all(),
                    'request' => $request->request->all(),
                    'attributes' => $request->attributes->all(),
                    'cookies' => [],
                    'files' => [],
                    'server' => Arr::except($request->server->all(), ['HTTP_AUTHORIZATION']),
                    'content' => $request->getContent(),
                ]
                : null,
            'user' => $this->getSerializedPropertyValue($context->user()),
        ]);
    }

    public function unserialize(string $context): GraphQLContext
    {
        [
            'request' => $rawRequest,
            'user' => $rawUser
        ] = unserialize($context);

        if ($rawRequest) {
            $request = new Request(
                $rawRequest['query'],
                $rawRequest['request'],
                $rawRequest['attributes'],
                $rawRequest['cookies'],
                $rawRequest['files'],
                $rawRequest['server'],
                $rawRequest['content'],
            );
            $request->setUserResolver(fn () => $this->getRestoredPropertyValue($rawUser));
        } else {
            $request = null;
        }

        return $this->createsContext->generate($request);
    }
}
