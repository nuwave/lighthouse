<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Encode and decode globally unique IDs.
 */
interface GlobalId
{
    /**
     * Encode a type and an id to create a Global ID.
     *
     * @param  string|int  $id
     */
    public function encode(string $type, $id): string;

    /**
     * Decode a Global ID into the type and the id it contains.
     *
     * @return array Contains [$type, $id], e.g. ['User', '123']
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
