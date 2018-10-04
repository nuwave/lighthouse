<?php

namespace Nuwave\Lighthouse\Support\Traits;

/**
 * Trait HandlesGlobalId
 * @package Nuwave\Lighthouse\Support\Traits
 *
 * @deprecated in favour of Nuwave\Lighthouse\Execution\Utils\GlobalId and Nuwave\Lighthouse\Execution\Utils\Cursor
 */
trait HandlesGlobalId
{
    /**
     * Create global id.
     *
     * @param string     $type
     * @param string|int $id
     *
     * @return string
     */
    public function encodeGlobalId($type, $id)
    {
        $resolver = config('lighthouse.globalId.encode');

        if (is_callable($resolver)) {
            return $resolver($type, $id);
        }

        return base64_encode($type.':'.$id);
    }

    /**
     * Decode the global id.
     *
     * @param string $id
     *
     * @return array
     */
    public function decodeGlobalId($id)
    {
        return explode(':', base64_decode($id));
    }

    /**
     * Get the decoded id.
     *
     * @param string $id
     *
     * @return string
     */
    public function decodeRelayId($id)
    {
        $resolver = config('lighthouse.globalId.decodeId');

        if (is_callable($resolver)) {
            return $resolver($id);
        }

        list($type, $id) = $this->decodeGlobalId($id);

        return $id;
    }

    /**
     * Get the decoded GraphQL Type.
     *
     * @param string $id
     *
     * @return string
     */
    public function decodeRelayType($id)
    {
        $resolver = config('lighthouse.globalId.decodeType');

        if (is_callable($resolver)) {
            return $resolver($id);
        }

        list($type, $id) = $this->decodeGlobalId($id);

        return $type;
    }

    /**
     * Decode cursor from query arguments.
     *
     * @param array $args
     *
     * @return int
     */
    protected function decodeCursor(array $args)
    {
        $resolver = config('lighthouse.globalId.decodeCursor');

        if (is_callable($resolver)) {
            return $resolver($args);
        }

        return isset($args['after']) && ! empty($args['after'])
            ? $this->getCursorId($args['after'])
            : 0;
    }

    /**
     * Get id from encoded cursor.
     *
     * @param string $cursor
     *
     * @return int
     */
    protected function getCursorId($cursor)
    {
        $resolver = config('lighthouse.globalId.getCursorId');

        if (is_callable($resolver)) {
            return $resolver($cursor);
        }

        return (int) $this->decodeRelayId($cursor);
    }
}
