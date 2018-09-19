<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions;

use Illuminate\Support\Str;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\ContextSerializer;

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
     * @param mixed       $root
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $info
     *
     * @return Subscription
     */
    public static function initialize($root, $args, $context, ResolveInfo $info)
    {
        $instance = new static();
        $instance->channel = 'private-'.(string) Str::uuid();
        $instance->context = $context;
        $instance->args = $args;
        $instance->operationName = $info->operation->name->value;
        $instance->queryString = $context->request->input('query');

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
     * @return ContextSerializer
     */
    protected function serializer()
    {
        return app(ContextSerializer::class);
    }
}
