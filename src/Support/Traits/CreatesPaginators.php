<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

trait CreatesPaginators
{
    // TODO: Ugh, get rid of this...
    use CanParseTypes, HandlesDirectives;

    /**
     * Register connection w/ schema.
     *
     * @param FieldValue $value
     */
    protected function registerConnection(FieldValue $value)
    {
        $schema = sprintf(
            'type Connection { connection(first: Int! after: String): String }
            type %s { node: %s cursor: String! }
            type %s { pageInfo: PageInfo! @field(class: "%s" method: "%s") edges: [%s] @field(class: "%s" method: "%s") }',
            $this->connectionEdgeName($value),
            $this->unpackNodeToString($value->getField()),
            $this->connectionTypeName($value),
            addslashes(ConnectionField::class),
            'pageInfoResolver',
            $this->connectionEdgeName($value),
            addslashes(ConnectionField::class),
            'edgeResolver'
        );

        collect($this->parseSchema($schema)->definitions)
            ->map(function ($node) use ($value) {
                if ('Connection' === $node->name->value) {
                    $connectionField = data_get($node, 'fields.0');
                    $field = $value->getField();
                    $field->arguments = $connectionField->arguments->merge($field->arguments);

                    return null;
                }

                return $this->convertNode($node);
            })
            ->filter()
            ->each(function ($type) use ($value) {
                schema()->type($type);

                if (ends_with($type->name, 'Connection')) {
                    $value->setType($type);
                }
            });
    }

    /**
     * Register paginator w/ schema.
     *
     * @param FieldValue $value
     */
    protected function registerPaginator(FieldValue $value)
    {
        $schema = sprintf(
            'type Paginator { paginator(count: Int! page: Int): String }
            type %s { paginatorInfo: PaginatorInfo! @field(class: "%s" method: "%s") data: [%s!]! @field(class: "%s" method: "%s") }',
            $this->paginatorTypeName($value),
            addslashes(PaginatorField::class),
            'paginatorInfoResolver',
            $this->unpackNodeToString($value->getField()),
            addslashes(PaginatorField::class),
            'dataResolver'
        );

        collect($this->parseSchema($schema)->definitions)
            ->map(function ($node) use ($value) {
                if ('Paginator' === $node->name->value) {
                    $paginatorField = data_get($node, 'fields.0');
                    $field = $value->getField();
                    $field->arguments = $paginatorField->arguments->merge($field->arguments);

                    return null;
                }

                return $this->convertNode($node);
            })
            ->filter()
            ->each(function ($type) use ($value) {
                schema()->type($type);

                if (ends_with($type->name, 'Paginator')) {
                    $value->setType($type);
                }
            });
    }

    /**
     * Get paginator type name.
     *
     * @param FieldValue $value
     *
     * @return string
     */
    protected function paginatorTypeName(FieldValue $value)
    {
        $parent = $value->getNodeName();
        $child = str_singular($value->getField()->name->value);

        return studly_case($parent.'_'.$child.'_Paginator');
    }

    /**
     * Get connection type name.
     *
     * @param FieldValue $value
     *
     * @return string
     */
    protected function connectionTypeName(FieldValue $value)
    {
        $parent = $value->getNodeName();
        $child = str_singular($value->getField()->name->value);

        return studly_case($parent.'_'.$child.'_Connection');
    }

    /**
     * Get connection edge name.
     *
     * @param FieldValue $value
     *
     * @return string
     */
    protected function connectionEdgeName(FieldValue $value)
    {
        $parent = $value->getNodeName();
        $child = str_singular($value->getField()->name->value);

        return studly_case($parent.'_'.$child.'_Edge');
    }
}
