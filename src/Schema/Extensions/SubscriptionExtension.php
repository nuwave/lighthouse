<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class SubscriptionExtension extends GraphQLExtension
{
    /**
     * @var SubscriptionRegistry
     */
    protected $registry;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $currentQuery = '';

    /**
     * @param SubscriptionRegistry $registry
     */
    public function __construct(SubscriptionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Extension name.
     *
     * @return string
     */
    public static function name(): string
    {
        return 'lighthouse_subscriptions';
    }

    /**
     * Handle request start.
     *
     * @param ExtensionRequest $request
     */
    public function requestDidStart(ExtensionRequest $request)
    {
        $this->request = $request->request();
        $this->currentQuery = $request->isBatchedRequest()
            ? array_get($this->request->toArray(), '0.query', '')
            : $this->request->input('query', '');
    }

    /**
     * Handle batch request start.
     *
     * @param int index
     */
    public function batchedQueryDidStart($index)
    {
        $this->registry->reset();
        $this->currentQuery = array_get($this->request->toArray(), "{$index}.query", '');
    }

    /**
     * Format extension output.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => 1,
            'channels' => $this->registry->toArray(),
        ];
    }

    /**
     * Get the current query.
     *
     * @return string
     */
    public function currentQuery(): string
    {
        return $this->currentQuery;
    }
}
