<?php

/**
 * @property-read string $bar
 * @property-read int|null $optional
 * @property-read GeneratedInput $input
 */
abstract class FooResolver
{
    /** @var array accessible through magic getters */
    private array $args;

    protected ResolveInfo $resolveInfo;

    abstract public function __invoke();
}

class Foo extends FooResolver
{
    public function __invoke(): Collection
    {
        $query = DB::query($this->bar);
        if ($this->has('optional')) {
            $query->where('optional', $this->optional);
        }

        $result = $query->get();

        return $result->update($this->input->toArray());
    }
}
