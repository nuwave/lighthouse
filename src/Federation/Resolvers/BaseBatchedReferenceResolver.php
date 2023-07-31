<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Introspection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Federation\BatchedEntityResolver;

abstract class BaseBatchedReferenceResolver implements BatchedEntityResolver
{
    /**
     * @var array<string, array{__typename: string, id: int}> $representations
     */
    public array $representations;

    /**
     * @var string
     */
    private string $primaryKey;

    /**
     * @var array
     */
    private array $representationIds;

    /**
     * Returns entities that were found based on the provided identifiers from $representations.
     *
     * @return Collection
     */
    public abstract function resolve(): Collection;

    /**
     * {@inheritDoc}
     */
    public function __invoke(array $representations): iterable
    {
        $this->representations = $representations;

        $entities = $this->resolve();

        return $this->response($entities);
    }

    /**
     * The returned iterable must have the same keys as the given array $representations
     * to enable Lighthouse to return the results in the correct order.
     *
     * @param Collection $entities
     * @return iterable
     */
    public function response(Collection $entities): iterable
    {
        $result = [];

        foreach ($this->representations as $hash => $representation) {
            $primaryKeyValue = Arr::get($representation, $this->getPrimaryKey());

            $result[$hash] = $entities->where($this->getPrimaryKey(), $primaryKeyValue)->first();
        }

        return $result;
    }

    /**
     * Returns an array of identifiers from the input representations array.
     *
     * @return array
     */
    public function getRepresentationIds(): array
    {
        if (!isset($this->representationIds)) {
            $this->representationIds = Arr::pluck($this->representations, $this->getPrimaryKey());
        }

        return $this->representationIds;
    }

    /**
     * Returns primary key from the input representations array.
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        if (!isset($this->primaryKey)) {
            $this->primaryKey = array_keys(Arr::except(Arr::first($this->representations), Introspection::TYPE_NAME_FIELD_NAME))[0];
        }

        return $this->primaryKey;
    }
}
