<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

interface RelayType
{
    /**
     * Get customer by id.
     *
     * When the root 'node' query is called, it will use this method
     * to resolve the type by providing the id.
     *
     * @param    string $id
     * @return  User
     */
    public function resolveById($id);
}
