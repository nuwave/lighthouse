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
     * @param  string  $type
     * @param  string|int  $id
     * @return string
     */
    public function encode(string $type, $id): string;

    /**
     * Decode a Global ID into the type and the id it contains.
     *
     * @param  string  $globalID
     * @return array Contains [$type, $id], e.g. ['User', '123']
     */
    public function decode(string $globalID): array;

    /**
     * Decode the Global ID and get just the ID.
     *
     * @param  string  $globalID
     * @return string
     */
    public function decodeID(string $globalID): string;

    /**
     * Decode the Global ID and get just the type.
     *
     * @param  string  $globalID
     * @return string
     */
    public function decodeType(string $globalID): string;
}
