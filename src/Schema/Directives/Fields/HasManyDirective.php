<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
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
     * @param FieldDefinitionNode $field
     *
     * @return \Closure
     */
    public function handle(FieldDefinitionNode $field)
    {
        return function ($parent, array $args) use ($field) {
            // TODO: Wrap w/ data loader to prevent N+1
            $builder = call_user_func([$parent, $this->getRelationshipName($field)]);
            // TODO: Create scopeGqlQuery scope to allow adjustments for $args.
            return $builder->get();
        };
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
}
