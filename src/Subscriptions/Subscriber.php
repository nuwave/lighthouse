<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Utils\AST;
use GraphQL\Error\Error;
use Illuminate\Support\Str;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;

class Subscriber implements \Serializable
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
     * @throws Error
     */
    public function __construct(
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $operationName = $resolveInfo->operation->name;
        if (! $operationName) {
            throw new Error(
                self::MISSING_OPERATION_NAME
            );
        }
        $this->operationName = $operationName->value;

        $this->channel = $this->uniqueChannelName();
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
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber
     */
    public function unserialize($subscription): Subscriber
    {
        $data = json_decode($subscription, true);

        $this->channel = $data['channel'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context']
        );
        $this->args = $data['args'];

        $this->operationName = $data['operation_name'];
        $this->query = AST::fromArray(
            \unserialize($data['query'])
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
            'channel' => $this->channel,
            'context' => $this->contextSerializer()->serialize($this->context),
            'args' => $this->args,
            'operation_name',
            'query' => \serialize(
                AST::toArray($this->query)
            ),
        ]);
    }

    /**
     * Set root data.
     *
     * @param  mixed  $root
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber
     */
    public function setRoot($root): Subscriber
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Generate a unique private channel name.
     *
     * @return string
     */
    protected function uniqueChannelName(): string
    {
        return 'private-'.Str::random(32).'-'.time();
    }

    /**
     * @return \Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer
     */
    protected function contextSerializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
