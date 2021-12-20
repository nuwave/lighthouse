<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheKeyAndTags
{
    /**
     * The ID of the fields root value, if present.
     *
     * @var int|string|null
     */
    protected $rootID;

    /** @var array<string, mixed> */
    protected $args;

    /** @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext */
    protected $context;

    /** @var \GraphQL\Type\Definition\ResolveInfo */
    protected $resolveInfo;

    /** @var bool */
    protected $isPrivate;

    /**
     * @param  int|string|null  $rootID
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        $rootID,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo,
        bool $isPrivate
    ) {
        $this->rootID = $rootID;
        $this->args = $args;
        $this->context = $context;
        $this->resolveInfo = $resolveInfo;
        $this->isPrivate = $isPrivate;
    }

    public function key(): string
    {
        // TODO consider adding a prefix
        $parts = [];

        $user = $this->context->user();
        if ($this->isPrivate && null !== $user) {
            $parts[] = 'auth';
            $parts[] = $user->getAuthIdentifier();
        }

        $parts[] = $this->resolveInfo->parentType->name;
        $parts[] = $this->rootID;
        $parts[] = $this->resolveInfo->fieldName;

        \Safe\ksort($this->args);
        foreach ($this->args as $key => $value) {
            $parts[] = $key;
            $parts[] = is_array($value)
                ? \Safe\json_encode($value)
                : $value;
        }

        return implode(':', $parts);
    }

    /**
     * @return array{string, string}
     */
    public function tags(): array
    {
        $parentTag = implode(':', [
            'graphql',
            $this->resolveInfo->parentType->name,
            $this->rootID,
        ]);

        $fieldTag = implode(':', [
            'graphql',
            $this->resolveInfo->parentType->name,
            $this->rootID,
            $this->resolveInfo->fieldName
        ]);

        return [$parentTag, $fieldTag];
    }
}
