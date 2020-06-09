<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Encode and decode globally unique IDs.
 */
interface GlobalId
{
    /**
     * Glue together a type and an id to create a global id.
     *
     * @param  string|int  $id
     */
    public function encode(string $type, $id): string;

    /**
     * Split a global id into the type and the id it contains.
     *
     * @return array<int, string|mixed> A tuple of [$type, $id], e.g. ['User', '123']
     */
    public function decode(string $globalID): array;

    /**
     * Decode the Global ID and get just the ID.
     */
    public function decodeID(string $globalID): string;

    /**
     * Decode the Global ID and get just the type.
     */
    public function decodeType(string $globalID): string;
}
