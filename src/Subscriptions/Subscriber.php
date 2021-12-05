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
     * A unique key for the subscriber's channel.
     *
     * This has to be unique for each subscriber, because each of them can send a different
     * query and must receive a response that is specifically tailored towards that.
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
     *
     * @see \GraphQL\Validator\Rules\SingleFieldSubscription
     *
     * @var string
     */
    public $fieldName;

    /**
     * The root element of the query.
     *
     * @var mixed can be anything
     */
    public $root;

    /**
     * The args passed to the subscription query.
     *
     * @var array<string, mixed>
     */
    public $args;

    /**
     * The variables passed to the subscription query.
     *
     * @var array<string, mixed>
     */
    public $variables;

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
        $this->variables = $resolveInfo->variableValues;
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
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'channel' => $this->channel,
            'topic' => $this->topic,
            'query' => serialize(
                AST::toArray($this->query)
            ),
            'field_name' => $this->fieldName,
            'args' => $this->args,
            'variables' => $this->variables,
            'context' => $this->contextSerializer()->serialize($this->context),
        ];
    }

    /**
     * @deprecated TODO remove in v6
     */
    public function serialize(): string
    {
        return \Safe\json_encode($this->__serialize());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->channel = $data['channel'];
        $this->topic = $data['topic'];

        /**
         * We know the type since it is set during construction and serialized.
         *
         * @var \GraphQL\Language\AST\DocumentNode $documentNode
         */
        $documentNode = AST::fromArray(
            unserialize($data['query'])
        );
        $this->query = $documentNode;
        $this->fieldName = $data['field_name'];
        $this->args = $data['args'];
        $this->variables = $data['variables'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context']
        );
    }

    /**
     * @deprecated TODO remove in v6
     */
    public function unserialize($subscription): void
    {
        $this->__unserialize(\Safe\json_decode($subscription, true));
    }

    /**
     * Set root data.
     *
     * @deprecated set the attribute directly
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
        return 'private-lighthouse-' . Str::random(32) . '-' . time();
    }

    protected function contextSerializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
