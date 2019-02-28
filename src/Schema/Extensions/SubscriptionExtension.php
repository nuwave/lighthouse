<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class SubscriptionExtension extends GraphQLExtension
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $registry;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $currentQuery = '';

    /**
     * @param  \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry  $registry
     * @return void
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
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest  $request
     * @return void
     */
    public function requestDidStart(ExtensionRequest $request): void
    {
        $this->request = $request->request();
        $this->currentQuery = $request->isBatchedRequest()
            ? Arr::get($this->request->toArray(), '0.query', '')
            : $this->request->input('query', '');
    }

    /**
     * Handle batch request start.
     *
     * @param  int  index
     *
     * @return void
     */
    public function batchedQueryDidStart(int $index): void
    {
        $this->registry->reset();
        $this->currentQuery = Arr::get($this->request->toArray(), "{$index}.query", '');
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
