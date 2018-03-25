<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class FieldDirective implements FieldResolver
{
    use HandlesDirectives;

    /**
     * Field resolver.
     *
     * @var string
     */
    protected $resolver;

    /**
     * Field resolver method.
     *
     * @var string
     */
    protected $method;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'field';
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
        $directive = $this->fieldDirective($value->getField(), $this->name());
        $resolver = $this->getResolver($value, $directive);
        $method = $this->getMethod($directive);
        $data = $this->argValue(collect($directive->arguments)->first(function ($arg) {
            return 'args' === data_get($arg, 'name.value');
        }));

        return $value->setResolver(function ($root, array $args, $context = null, $info = null) use ($resolver, $method, $data) {
            $instance = app($resolver);

            return call_user_func_array(
                [$instance, $method],
                [$root, array_merge($args, ['directive' => $data]), $context, $info]
            );
        });
    }

    /**
     * Get resolver namespace.
     *
     * @param FieldValue    $value
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getResolver(FieldValue $value, DirectiveNode $directive)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            $className = array_get(explode('@', $resolver), '0');

            return $value->getNode()->getNamespace($className);
        }

        return $value->getNode()->getNamespace(
            $this->getClassName($directive)
        );
    }

    /**
     * Get class name for resolver.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getClassName(DirectiveNode $directive)
    {
        $class = $this->directiveArgValue($directive, 'class');

        if (! $class) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `class` argument.',
                $directive->name->value
            ));
        }

        return $class;
    }

    /**
     * Get method for resolver.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getMethod(DirectiveNode $directive)
    {
        if ($resolver = $this->directiveArgValue($directive, 'resolver')) {
            if ($method = array_get(explode('@', $resolver), '1')) {
                return $method;
            }
        }

        $method = $this->directiveArgValue($directive, 'method');

        if (! $method) {
            throw new DirectiveException(sprintf(
                'Directive [%s] must have a `method` argument.',
                $directive->name->value
            ));
        }

        return $method;
    }
}
