<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\SubscriptionException;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Serializable;

class Subscriber implements Serializable
{
    public const MISSING_OPERATION_NAME = 'Must pass an operation name when using a subscription.';

    /**
     * @var string
     */
    public $channel;

    public $root;

    /**
     * @var array
     */
    public $args;

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
     * @return $this
     */
    public function setRoot($root): self
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Generate a unique private channel name.
     */
    public static function uniqueChannelName(): string
    {
        return 'private-lighthouse-'.Str::random(32).'-'.time();
    }

    protected function contextSerializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
