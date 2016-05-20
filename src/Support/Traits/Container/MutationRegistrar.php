<?php

namespace Nuwave\Relay\Support\Traits\Container;

trait MutationRegistrar
{
    /**
     * Registered GraphQL mutations.
     *
     * @var array
     */
    protected $mutations = [];

    /**
     * Add new mutation to collection.
     *
     * @param mixed $mutation
     * @param string $name
     * @return boolean
     */
    public function addMutation($mutation, $name)
    {
        $this->mutations = array_merge($this->mutations, [
            $name => $mutation
        ]);

        return true;
    }

    /**
     * Get registered mutation.
     *
     * @param  string $mutation
     * @return mixed
     */
    public function getMutation($mutation)
    {
        return $this->getMutations()->get($mutation);
    }

    /**
     * Get collection of mutations.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getMutations()
    {
        return collect($this->mutations);
    }
}
