<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\QueryResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class AuthDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'auth';
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
        $guard = $this->directiveArgValue(
            $this->fieldDirective($field, $this->name()),
            'guard'
        );

        return QueryResolver::resolve($field, function () use ($guard) {
            return auth($guard)->user();
        });
    }
}
