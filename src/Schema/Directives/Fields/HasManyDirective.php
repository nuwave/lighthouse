<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\DataLoader\Loaders\HasManyLoader;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CreatesPaginators;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class HasManyDirective implements FieldResolver
{
    use CreatesPaginators, HandlesGlobalId;

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
        $this->registerConnection($value);
        $scopes = $this->getScopes($value);

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation, $scopes) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'parent', 'args', 'scopes'),
                ['type' => 'relay']
            ), camel_case($parent->getTable().'_'.$relation));
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
        $this->registerPaginator($value);
        $scopes = $this->getScopes($value);

        return function ($parent, array $args, $context = null, ResolveInfo $info = null) use ($relation, $scopes) {
            return graphql()->batch(HasManyLoader::class, $parent->getKey(), array_merge(
                compact('relation', 'parent', 'args', 'scopes'),
                ['type' => 'paginator']
            ), camel_case($parent->getTable().'_'.$relation));
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
                compact('relation', 'parent', 'args', 'scopes'),
                ['type' => 'default']
            ), camel_case($parent->getTable().'_'.$relation));
        };
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
            $this->fieldDirective($value->getField(), $this->name()),
            'scopes',
            []
        );
    }
}
