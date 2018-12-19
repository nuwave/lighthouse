<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Str;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\SubscriptionException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension as Extension;

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
     * @param array          $args
     * @param GraphQLContext $context
     * @param ResolveInfo    $info
     * @param Extension      $extension
     * @param string         $queryString
     *
     * @return Subscription
     */
    public static function initialize(
        array $args,
        GraphQLContext $context,
        ResolveInfo $info,
        string $queryString
    ) {
        if (null === $info->operation->name) {
            throw new SubscriptionException('An operation name must be present on a subscription request.');
        }

        $instance = new static();

        $instance->channel = $instance->channel();
        $instance->context = $context;
        $instance->args = $args;
        $instance->operationName = $info->operation->name->value;
        $instance->queryString = $queryString;

        return $instance;
    }

    /**
     * Unserialize subscription.
     *
     * @param string $subscription
     *
     * @return Subscription
     */
    public static function unserialize($subscription)
    {
        $data = json_decode($subscription, true);
        $instance = new static();
        $instance->channel = array_get($data, 'channel');
        $instance->context = $instance->serializer()->unserialize(
            array_get($data, 'context')
        );
        $instance->args = array_get($data, 'args', []);
        $instance->operationName = array_get($data, 'operation_name');
        $instance->queryString = array_get($data, 'query_string');

        return $instance;
    }

    /**
     * Set root data.
     *
     * @param mixed $root
     *
     * @return Subscriber
     */
    public function setRoot($root)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Serialized subscription.
     *
     * @return string
     */
    public function toArray()
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
     * Generate channel name.
     *
     * @return string
     */
    protected function channel()
    {
        return 'private-'.(string) str_random(32).'-'.time();
    }

    /**
     * @return ContextSerializer
     */
    protected function serializer()
    {
        return app(ContextSerializer::class);
    }
}
