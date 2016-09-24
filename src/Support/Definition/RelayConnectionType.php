<?php

namespace Nuwave\Lighthouse\Support\Definition;

use Closure;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;

class RelayConnectionType extends GraphQLType
{
    /**
     * Type of node at the end of this connection.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    protected $edgeType;

    /**
     * PageInfo Type.
     *
     * @var \GraphQL\Type\Definition\ObjectType
     */
    protected $pageInfoType;

    /**
     * The edge resolver for this connection type
     *
     * @var \Closure
     */
    protected $edgeResolver;

    /**
     * The pageInfo resolver for this connection type.
     *
     * @var \Closure
     */
    protected $pageInfoResolver;

    /**
     * The name of the edge (i.e. `User`).
     *
     * @var string
     */
    protected $name = '';

    /**
     * Special fields present on this connection type.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }

    /**
     * Fields that exist on every connection.
     *
     * @return array
     */
    protected function baseFields()
    {
        return [
            'pageInfo' => [
                'type' => Type::nonNull($this->pageInfoType),
                'description' => 'Information to aid in pagination.',
                'resolve' => function ($collection) {
                    return $collection;
                },
            ],
            'edges' => [
                'type' => Type::listOf($this->edgeType),
                'description' => 'Information to aid in pagination.',
                'resolve' => function ($collection) {
                    return $this->injectCursor($collection);
                },
            ]
        ];
    }

    /**
     * Get the default arguments for a connection.
     *
     * @return array
     */
    public static function connectionArgs()
    {
        return [
            'after' => [
                'type' => Type::string()
            ],
            'first' => [
                'type' => Type::int()
            ],
            'before' => [
                'type' => Type::string()
            ],
            'last' => [
                'type' => Type::int()
            ],
            'page' => [
                'type' => Type::int(),
            ]
        ];
    }

    /**
     * Inject encoded cursor into collection items.
     *
     * @param  mixed $collection
     * @return mixed
     */
    protected function injectCursor($collection)
    {
        if ($collection instanceof LengthAwarePaginator) {
            $page = $collection->currentPage();
            $encoder = app('graphql')->cursorEncoder($this->name);

            $collection->values()->each(function ($item, $x) use ($page, $encoder) {
                $cursor        = ($x + 1) * $page;
                $encodedCursor = is_callable($encoder) ? $encoder($item, $x, $page) : $this->encodeGlobalId('arrayconnection', $cursor);
                if (is_array($item)) {
                    $item['relayCursor'] = $encodedCursor;
                } else {
                    if (is_object($item) && is_array($item->attributes)) {
                        $item->attributes['relayCursor'] = $encodedCursor;
                    } else {
                        $item->relayCursor = $encodedCursor;
                    }
                }
            });
        }

        return $collection;
    }

    /**
     * Get id from encoded cursor.
     *
     * @param  string $cursor
     * @return integer
     */
    protected function getCursorId($cursor)
    {
        $decoder = app('graphql')->cursorEncoder($this->name);

        if (is_callable($decoder)) {
            return $decoder($cursor);
        }

        return (int)$this->decodeRelayId($cursor);
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $fields = array_merge($this->baseFields(), $this->fields());

        return [
            'name' => ucfirst($this->name),
            'description' => 'A connection to a list of items.',
            'fields' => $fields,
            'resolve' => function ($root, $args, $context, ResolveInfo $info) {
                return $this->resolve($root, $args, $context, $info, $this->name);
            }
        ];
    }

    /**
     * Create the instance of the connection type.
     *
     * @param Closure $pageInfoResolver
     * @param Closure $edgeResolver
     * @return ObjectType
     */
    public function toType(Closure $pageInfoResolver = null, Closure $edgeResolver = null)
    {
        $this->pageInfoResolver = $pageInfoResolver;

        $this->edgeResolver = $edgeResolver;

        return new ObjectType($this->toArray());
    }

    /**
     * Set name of connection.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Set instance of edge type.
     *
     * @param ObjectType $type
     */
    public function setEdgeType(ObjectType $type)
    {
        $this->edgeType = $type;
    }

    /**
     * Set instance of page info type.
     *
     * @param ObjectType $type
     */
    public function setPageInfoType(ObjectType $type)
    {
        $this->pageInfoType = $type;
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();

        return isset($attributes[$key]) ? $attributes[$key] : null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->getAttributes()[$key]);
    }

    /**
     * Get the type of nodes at the end of this connection.
     *
     * @return mixed
     */
    public function type()
    {
        return null;
    }
}
