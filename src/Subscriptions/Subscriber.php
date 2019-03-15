<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Serializable;
use GraphQL\Utils\AST;
use Illuminate\Support\Str;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\SubscriptionException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Subscriber implements Serializable
{
    const MISSING_OPERATION_NAME = 'Must pass an operation name when using a subscription.';

    /**
     * @var string
     */
    public $channel;

    /**
     * @var mixed
     */
    public $root;

    /**
     * @var array
     */
    public $args;

    /**
     * @var mixed
     */
    public $context;

    /**
     * @var \GraphQL\Language\AST\DocumentNode
     */
    public $query;

    /**
     * @var string
     */
    public $operationName;

    /**
     * @param  mixed[]  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\SubscriptionException
     */
    public function __construct(
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $operationName = $resolveInfo->operation->name;
        if (! $operationName) {
            throw new SubscriptionException(
                self::MISSING_OPERATION_NAME
            );
        }
        $this->operationName = $operationName->value;

        $this->channel = self::uniqueChannelName();
        $this->args = $args;
        $this->context = $context;

        $documentNode = new DocumentNode([]);
        $documentNode->definitions = $resolveInfo->fragments;
        $documentNode->definitions[] = $resolveInfo->operation;
        $this->query = $documentNode;
    }

    /**
     * Unserialize subscription from a JSON string.
     *
     * @param  string  $subscription
     * @return $this
     */
    public function unserialize($subscription): self
    {
        $data = json_decode($subscription, true);

        $this->operationName = $data['operation_name'];
        $this->channel = $data['channel'];
        $this->args = $data['args'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context']
        );
        $this->query = AST::fromArray(
            unserialize($data['query'])
        );

        return $this;
    }

    /**
     * Convert this into a JSON string.
     *
     * @return false|string
     */
    public function serialize()
    {
        return json_encode([
            'operation_name' => $this->operationName,
            'channel' => $this->channel,
            'args' => $this->args,
            'context' => $this->contextSerializer()->serialize($this->context),
            'query' => serialize(
                AST::toArray($this->query)
            ),
        ]);
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
     * Generate a unique private channel name.
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
    protected function contextSerializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
