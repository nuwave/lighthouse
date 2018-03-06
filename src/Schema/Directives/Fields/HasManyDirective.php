<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Types\ConnectionField;
use Nuwave\Lighthouse\Schema\Types\PaginatorField;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
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
                    $this->defaultResolver($relation)
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
        $schema = sprintf(
            'type %s { node: %s cursor: String! }
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
            ->map(function ($node) {
                return $this->convertNode($node);
            })
            ->each(function ($type) use ($value) {
                schema()->type($type);

                if (ends_with($type->name, 'Connection')) {
                    $value->setType($type);
                }
            });

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation) {
            $builder = call_user_func([$parent, $relation]);

            return $builder->relayConnection($args);
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
        $schema = sprintf(
            'type %s { paginatorInfo: PaginatorInfo! @field(class: "%s" method: "%s") data: [%s!]! @field(class: "%s" method: "%s") }',
            $this->paginatorTypeName($value),
            addslashes(PaginatorField::class),
            'paginatorInfoResolver',
            $this->unpackNodeToString($value->getField()),
            addslashes(PaginatorField::class),
            'dataResolver'
        );

        collect($this->parseSchema($schema)->definitions)
            ->map(function ($node) {
                return $this->convertNode($node);
            })
            ->each(function ($type) use ($value) {
                schema()->type($type);

                if (ends_with($type->name, 'Paginator')) {
                    $value->setType($type);
                }
            });

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation) {
            $builder = call_user_func([$parent, $relation]);

            return $builder->paginatorConnection($args);
        };
    }

    /**
     * Use default resolver for field.
     *
     * @param string $relation
     *
     * @return \Closure
     */
    protected function defaultResolver($relation)
    {
        return function ($parent, array $args) use ($relation) {
            // TODO: Wrap w/ data loader to prevent N+1
            $builder = call_user_func([$parent, $relation]);
            // TODO: Create scopeGqlQuery scope to allow adjustments for $args.
            return $builder->get();
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
        $parent = $value->getNode()->name->value;
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
        $parent = $value->getNode()->name->value;
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
        $parent = $value->getNode()->name->value;
        $child = str_singular($value->getField()->name->value);

        return studly_case($parent.'_'.$child.'_Edge');
    }
}
