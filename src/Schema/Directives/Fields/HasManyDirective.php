<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\HasManyLoader;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class HasManyDirective implements FieldResolver
{
    use CanParseTypes, HandlesDirectives, HandlesGlobalId;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'hasMany';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function handle(FieldValue $value)
    {
        $relation = $this->getRelationshipName($value->getField());
        $resolver = $this->getResolver($value->getField());

        if (! in_array($resolver, ['default', 'paginator', 'relay'])) {
            throw new DirectiveException(sprintf(
                '[%s] is not a valid `type` on `hasMany` directive [`paginator`, `relay`, `default`].',
                $resolver
            ));
        }

        switch ($resolver) {
            case 'paginator':
                return $value->setResolver(
                    $this->paginatorTypeResolver($relation, $value)
                );
            case 'relay':
                return $value->setResolver(
                    $this->connectionTypeResolver($relation, $value)
                );
            default:
                return $value->setResolver(
                    $this->defaultResolver($relation, $value)
                );
        }
    }

    /**
     * Get has many relationship name.
     *
     * @param FieldDefinitionNode $field
     *
     * @return string
     */
    protected function getRelationshipName(FieldDefinitionNode $field)
    {
        return $this->directiveArgValue(
            $this->fieldDirective($field, $this->name()),
            'relation',
            $field->name->value
        );
    }

    /**
     * Get resolver type.
     *
     * @param FieldDefinitionNode $field
     *
     * @return string
     */
    protected function getResolver(FieldDefinitionNode $field)
    {
        return $this->directiveArgValue(
            $this->fieldDirective($field, $this->name()),
            'type',
            'default'
        );
    }

    /**
     * Get connection type.
     *
     * @param string     $relation
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function connectionTypeResolver($relation, FieldValue $value)
    {
        $scopes = $this->getScopes($value);
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

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation, $scopes) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'root', 'args', 'scopes'),
                ['type' => 'relay']
            ));
        };
    }

    /**
     * Get paginator type resolver.
     *
     * @param string     $relation
     * @param FieldValue $value
     *
     * @return \Closure
     */
    protected function paginatorTypeResolver($relation, FieldValue $value)
    {
        $scopes = $this->getScopes($value);
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

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation, $scopes) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'root', 'args', 'scopes'),
                ['type' => 'paginator']
            ));
        };
    }

    /**
     * Use default resolver for field.
     *
     * @param FieldValue $value
     * @param string     $relation
     *
     * @return \Closure
     */
    protected function defaultResolver($relation, FieldValue $value)
    {
        $scopes = $this->getScopes($value);

        return function ($parent, array $args) use ($relation, $scopes) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'root', 'args', 'scopes'),
                ['type' => 'default']
            ));
        };
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

    /**
     * Get scope(s) to run on connection.
     *
     * @param FieldValue $value
     *
     * @return array
     */
    protected function getScopes(FieldValue $value)
    {
        return $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'hasMany'),
            'scopes',
            []
        );
    }
}
