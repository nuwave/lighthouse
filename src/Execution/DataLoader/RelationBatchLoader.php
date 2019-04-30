<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;

class RelationBatchLoader extends BatchLoader
{
    /**
     * The name of the Eloquent relation to load.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The arguments that were passed to the field.
     *
     * @var mixed[]
     */
    protected $args;

    /**
     * Names of the scopes that have to be called for the query.
     *
     * @var string[]
     */
    protected $scopes;

    /**
     * The ResolveInfo of the currently executing field.
     *
     * @var \GraphQL\Type\Definition\ResolveInfo
     */
    protected $resolveInfo;

    /**
     * Present when using pagination, the amount of rows to be fetched.
     *
     * @var int|null
     */
    protected $first;

    /**
     * Present when using pagination, the page to be fetched.
     *
     * @var int|null
     */
    protected $page;

    /**
     * @param  string  $relationName
     * @param  mixed[]  $args
     * @param  string[]  $scopes
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @param  int|null  $first
     * @param  int|null  $page
     * @return void
     */
    public function __construct(
        string $relationName,
        array $args,
        array $scopes,
        ResolveInfo $resolveInfo,
        ?int $first = null,
        ?int $page = null
    ) {
        $this->relationName = $relationName;
        $this->args = $args;
        $this->scopes = $scopes;
        $this->resolveInfo = $resolveInfo;
        $this->first = $first;
        $this->page = $page;
    }

    /**
     * Resolve the keys.
     *
     * @return mixed[]
     */
    public function resolve(): array
    {
        $modelRelationFetcher = $this->getRelationFetcher();

        if ($this->first !== null) {
            $modelRelationFetcher->loadRelationsForPage($this->first, $this->page);
        } else {
            $modelRelationFetcher->loadRelations();
        }

        return $modelRelationFetcher->getRelationDictionary($this->relationName);
    }

    /**
     * Construct a new instance of a relation fetcher.
     *
     * @return \Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher
     */
    protected function getRelationFetcher(): ModelRelationFetcher
    {
        return new ModelRelationFetcher(
            $this->getParentModels(),
            [$this->relationName => function ($query) {
                return $this->resolveInfo
                    ->builder
                    ->addScopes($this->scopes)
                    ->apply($query, $this->args);
            }]
        );
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @return \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Model>
     */
    protected function getParentModels(): Collection
    {
        return (new Collection($this->keys))->pluck('parent');
    }
}
