<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Resolvers\MutationResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
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
     * @param FieldValue $value
     *
     * @return \Closure
     */
    public function handle(FieldValue $value)
    {
        $namespace = $this->directiveArgValue($this->fieldDirective($value->getField(), 'mutation'), 'class');

        if (! $namespace) {
            throw new DirectiveException(sprintf(
                'The `mutation` directive on %s needs to include a `class` argument',
                $value->getField()->name->value
            ));
        }

        return $value->setResolver(
            MutationResolver::resolve($value->getField(), $this->getResolver($namespace))
        );
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
