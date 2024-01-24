<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Utils\AST;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

class Subscriber
{
    /**
     * A unique key for the subscriber's channel.
     *
     * This has to be unique for each subscriber, because each of them can send a different
     * query and must receive a response that is specifically tailored towards that.
     */
    public string $channel;

    /** X-Socket-ID header passed on the subscription query. */
    public ?string $socket_id;

    /** The topic subscribed to. */
    public string $topic;

    /** The contents of the query. */
    public DocumentNode $query;

    /**
     * The name of the queried field.
     *
     * Guaranteed to be unique because of @see \GraphQL\Validator\Rules\SingleFieldSubscription
     */
    public string $fieldName;

    /** The root element of the query. */
    public mixed $root;

    /**
     * The variables passed to the subscription query.
     *
     * @var array<string, mixed>
     */
    public array $variables = [];

    public function __construct(
        /**
         * The args passed to the subscription query.
         *
         * @var array<string, mixed> $args
         */
        public array $args,
        /** The context passed to the query. */
        public GraphQLContext $context,
        ResolveInfo $resolveInfo,
    ) {
        $this->fieldName = $resolveInfo->fieldName;
        $this->channel = self::uniqueChannelName();
        $this->variables = $resolveInfo->variableValues;

        $xSocketID = request()->header('X-Socket-ID');
        // @phpstan-ignore-next-line
        if (is_array($xSocketID)) {
            throw new \Exception('X-Socket-ID must be a string or null.');
        }

        $this->socket_id = $xSocketID;

        $this->query = new DocumentNode([
            'definitions' => new NodeList(array_merge(
                $resolveInfo->fragments,
                [$resolveInfo->operation],
            )),
        ]);
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        return [
            'socket_id' => $this->socket_id,
            'channel' => $this->channel,
            'topic' => $this->topic,
            'query' => serialize(
                AST::toArray($this->query),
            ),
            'field_name' => $this->fieldName,
            'args' => $this->args,
            'variables' => $this->variables,
            'context' => $this->contextSerializer()->serialize($this->context),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function __unserialize(array $data): void
    {
        $this->channel = $data['channel'];
        $this->topic = $data['topic'];

        $documentNode = AST::fromArray(
            unserialize($data['query']),
        );
        assert($documentNode instanceof DocumentNode, 'We know the type since it is set during construction and serialized.');

        $this->socket_id = $data['socket_id'];
        $this->query = $documentNode;
        $this->fieldName = $data['field_name'];
        $this->args = $data['args'];
        $this->variables = $data['variables'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context'],
        );
    }

    /** Generate a unique private channel name. */
    public static function uniqueChannelName(): string
    {
        return 'private-lighthouse-' . Str::random(32) . '-' . time();
    }

    protected function contextSerializer(): SerializesContext
    {
        return Container::getInstance()->make(SerializesContext::class);
    }
}
