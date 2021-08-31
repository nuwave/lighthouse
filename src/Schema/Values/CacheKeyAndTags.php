<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheKeyAndTags
{
    /** @var mixed|null */
    protected $root;

    /** @var array<string, mixed> */
    protected $args;

    /** @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext */
    protected $context;

    /** @var \GraphQL\Type\Definition\ResolveInfo */
    protected $resolveInfo;

    /** @var \Nuwave\Lighthouse\Schema\Values\FieldValue */
    protected $fieldValue;

    /** @var bool */
    protected $isPrivate;

    /** @var mixed */
    protected $fieldKey;

    /**
     * @param  mixed|null  $root
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        $root,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo,
        FieldValue $fieldValue,
        bool $isPrivate
    ) {
        $this->root = $root;
        $this->args = $args;
        $this->context = $context;
        $this->resolveInfo = $resolveInfo;
        $this->fieldValue = $fieldValue;
        $this->isPrivate = $isPrivate;

        $this->fieldKey = $this->fieldKey();
    }

    public function key(): string
    {
        $parts = [];

        $user = $this->context->user();
        if ($this->isPrivate && $user !== null) {
            $parts [] = 'auth';
            $parts [] = $user->getAuthIdentifier();
        }

        $parts [] = strtolower($this->resolveInfo->parentType->name);
        $parts [] = $this->fieldKey;
        $parts [] = strtolower($this->resolveInfo->fieldName);

        foreach ($this->convertInputArgumentsToKeys() as $argKey) {
            $parts [] = $argKey;
        }

        return $this->implode($parts);
    }

    /**
     * @return array{string, string}
     */
    public function tags(): array
    {
        $parent = strtolower($this->resolveInfo->parentType->name);

        $typeTag = $this->implode([
            'graphql',
            $parent,
            $this->fieldKey,
        ]);

        $fieldTag = $this->implode([
            'graphql',
            $parent,
            $this->fieldKey,
            $this->resolveInfo->fieldName,
        ]);

        return [$typeTag, $fieldTag];
    }

    /**
     * @return \Illuminate\Support\Collection<string>
     */
    protected function convertInputArgumentsToKeys(): Collection
    {
        return (new Collection($this->args))
            ->sortKeys()
            ->map(function ($value, $key): string {
                $keyValue = is_array($value)
                    ? \Safe\json_encode($value)
                    : $value;

                return "{$key}:{$keyValue}";
            });
    }

    /**
     * @return mixed Can be anything
     */
    protected function fieldKey()
    {
        if ($this->root === null) {
            return null;
        }

        $cacheFieldKey = $this->fieldValue
            ->getParent()
            ->getCacheKey();

        return data_get($this->root, $cacheFieldKey);
    }

    /**
     * @param  array<mixed|null> $items
     */
    protected function implode(array $items): string
    {
        return (new Collection($items))
            ->filter()
            ->values()
            ->implode(':');
    }
}
