<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Resolvers\QueryResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class QueryDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'query';
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
        $namespace = $this->directiveArgValue($this->fieldDirective($value->getField(), 'query'), 'class');

        if (! $namespace) {
            throw new DirectiveException(sprintf(
                'The `query` directive on %s needs to include a `class` argument',
                $value->getField()->name->value
            ));
        }

        return QueryResolver::resolve($value->getField(), $this->getResolver($value, $namespace));
    }

    /**
     * Get mutation resolver.
     *
     * @param FieldValue $value
     * @param string     $namespace
     *
     * @return \Closure
     */
    protected function getResolver(FieldValue $value, $namespace)
    {
        $query = app($namespace);

        $method = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'query'),
            'method',
            'resolve'
        );

        return (new \ReflectionClass($query))->getMethod($method)->getClosure($query);
    }
}
