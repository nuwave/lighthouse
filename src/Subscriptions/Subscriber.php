<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\SubscriptionException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Subscriber
{
    /**
     * @var string
     */
    public $channel;

    /**
     * @var mixed
     */
    public $context;

    /**
     * @var array
     */
    public $args;

    /**
     * @var string
     */
    public $operationName;

    /**
     * @var string
     */
    public $queryString;

    /**
     * @var mixed
     */
    public $root;

    /**
     * Create new subscription instance.
     *
     * @param  array  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @param  string  $queryString
     *
     * @return static
     */
    public static function initialize(
        array $args,
        GraphQLContext $context,
        ResolveInfo $info,
        string $queryString
    ): self {
        if ($info->operation->name === null) {
            throw new SubscriptionException('An operation name must be present on a subscription request.');
        }

        $instance = new static();

        $instance->channel = $instance->uniqueChannelName();
        $instance->context = $context;
        $instance->args = $args;
        $instance->operationName = $info->operation->name->value;
        $instance->queryString = $queryString;

        return $instance;
    }

    /**
     * Unserialize subscription.
     *
     * @param  string  $subscription
     *
     * @return static
     */
    public static function unserialize(string $subscription): self
    {
        $data = json_decode($subscription, true);
        $instance = new static();
        $instance->channel = Arr::get($data, 'channel');
        $instance->context = $instance->serializer()->unserialize(
            Arr::get($data, 'context')
        );
        $instance->args = Arr::get($data, 'args', []);
        $instance->operationName = Arr::get($data, 'operation_name');
        $instance->queryString = Arr::get($data, 'query_string');

        return $instance;
    }

    /**
     * Set root data.
     *
     * @param  mixed  $root
     * @return $this
     */
    public function setRoot($root): self
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Serialized subscription.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'context' => $this->serializer()->serialize($this->context),
            'args' => $this->args,
            'operation_name' => $this->operationName,
            'query_string' => $this->queryString,
        ];
    }

    /**
     * Generate a globally unique channel name.
     *
     * @return string
     */
    public static function uniqueChannelName(): string
    {
        return 'private-lighthouse-'.Str::random(32).'-'.time();
    }

    /**
     * @return \Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer
     */
    protected function serializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
