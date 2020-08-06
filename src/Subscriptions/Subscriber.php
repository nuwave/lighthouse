<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Serializable;

class Subscriber implements Serializable
{
    /**
     * A unique key for the subscriber.
     *
     * @var string
     */
    public $channel;

    /**
     * The topic subscribed to.
     *
     * @var string
     */
    public $topic;

    /**
     * The contents of the query.
     *
     * @var \GraphQL\Language\AST\DocumentNode
     */
    public $query;

    /**
     * The name of the queried field.
     *
     * Guaranteed be be unique because of
     * @see \GraphQL\Validator\Rules\SingleFieldSubscription
     *
     * @var string
     */
    public $fieldName;

    /**
     * The root element of the query.
     *
     * @var mixed Can be anything.
     */
    public $root;

    /**
     * The args passed to the subscription query.
     *
     * @var array<string, mixed>
     */
    public $args;

    /**
     * The context passed to the query.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public $context;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $this->fieldName = $resolveInfo->fieldName;
        $this->channel = self::uniqueChannelName();
        $this->args = $args;
        $this->context = $context;

        /**
         * Must be here, since webonyx/graphql-php validated the subscription.
         *
         * @var \GraphQL\Language\AST\OperationDefinitionNode $operation
         */
        $operation = $resolveInfo->operation;

        $this->query = new DocumentNode([
            'definitions' => new NodeList(array_merge(
                $resolveInfo->fragments,
                [$operation]
            )),
        ]);
    }

    /**
     * Unserialize subscription from a JSON string.
     *
     * @param  string  $subscription
     */
    public function unserialize($subscription): void
    {
        $data = \Safe\json_decode($subscription, true);

        $this->channel = $data['channel'];
        $this->topic = $data['topic'];
        $this->query = AST::fromArray( // @phpstan-ignore-line We know this will be exactly a DocumentNode and nothing else
            unserialize($data['query'])
        );
        $this->fieldName = $data['field_name'];
        $this->args = $data['args'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context']
        );
    }

    /**
     * Convert this into a JSON string.
     */
    public function serialize(): string
    {
        return \Safe\json_encode([
            'channel' => $this->channel,
            'topic' => $this->topic,
            'query' => serialize(
                AST::toArray($this->query)
            ),
            'field_name' => $this->fieldName,
            'args' => $this->args,
            'context' => $this->contextSerializer()->serialize($this->context),
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
