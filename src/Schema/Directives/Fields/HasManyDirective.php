<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class HasManyDirective implements FieldResolver
{
    use HandlesDirectives;

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
                return $this->paginatorResolver($relation);
            case 'relay':
                return $this->connectionResolver($relation);
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
        return function ($parent, array $args) use ($relation) {
            $builder = call_user_func([$parent, $relation]);

            return $builder->relayConnection($args);
        };
    }

    /**
     * Use paginator resolver for field.
     *
     * @param string $relation
     *
     * @return \Closure
     */
    protected function paginatorResolver($relation)
    {
        return function ($parent, array $args) use ($relation) {
            $builder = call_user_func([$parent, $relation]);

            return $builder->paginatorConnection($args);
        };
    }
}
