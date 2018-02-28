<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\MutationResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class MutationDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'mutation';
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
        $namespace = $this->directiveArgValue($this->fieldDirective($field, 'mutation'), 'class');

        if (! $namespace) {
            throw new DirectiveException(sprintf(
                'The `mutation` directive on %s needs to include a `class` argument',
                $field->name->value
            ));
        }

        return MutationResolver::resolve($field, $this->getResolver($namespace));
    }

    /**
     * Get mutation resolver.
     *
     * @param string $namespace
     *
     * @return \Closure
     */
    protected function getResolver($namespace)
    {
        $mutation = app($namespace);

        return (new \ReflectionClass($mutation))->getMethod('resolve')->getClosure($mutation);
    }
}
