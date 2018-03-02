<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class HasManyDirective implements FieldResolver
{
    use CanParseTypes, HandlesDirectives;

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
     * @return \Closure
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
                return $this->paginatorTypeResolver($relation, $value);
            case 'relay':
                return $this->connectionTypeResolver($relation, $value);
            default:
                return $this->defaultResolver($relation);
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
            type %s { pageInfo: PageInfo! edges: [%s] @field(class: "%s" method: "%s") }',
            $this->connectionEdgeName($value),
            $this->unpackNodeToString($value->getField()),
            $this->connectionTypeName($value),
            $this->connectionEdgeName($value),
            addslashes(self::class),
            'connectionResolver'
        );

        collect($this->getObjectTypes($this->parseSchema($schema)))
            ->each(function ($type) use ($value) {
                schema()->type(schema()->unpackType($type));

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
            'type %s { paginatorInfo: PaginatorInfo! data: [%s!]! @field(class: "%s" method: "%s") }',
            $this->paginatorTypeName($value),
            $this->unpackNodeToString($value->getField()),
            addslashes(self::class),
            'paginatorResolver'
        );

        collect($this->getObjectTypes($this->parseSchema($schema)))
            ->each(function ($type) use ($value) {
                schema()->type(schema()->unpackType($type));

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
     * Use connection resolver for field.
     *
     * @param string $relation
     *
     * @return \Closure
     */
    protected function connectionResolver($relation)
    {
        return function (LengthAwarePaginator $root, array $args, $context = null, ResolveInfo $info = null) {
            // TODO: Need to add cursor to edges...
            return ['pageInfo' => $root, 'edges' => $root->items()];
        };
    }

    /**
     * Use paginator resolver for field.
     *
     * @return \Closure
     */
    protected function paginatorResolver()
    {
        return function (LengthAwarePaginator $root, array $args, $context = null, ResolveInfo $info = null) {
            return ['pageInfo' => $root, 'data' => $root->items()];
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
