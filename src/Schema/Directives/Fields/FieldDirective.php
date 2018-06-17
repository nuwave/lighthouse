<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\CanParseResolvers;

class FieldDirective implements FieldResolver
{
    use CanParseResolvers;

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
    public function resolveField(FieldValue $value)
    {
        $directive = $this->fieldDirective($value->getField(), $this->name());
        $resolver = $this->getResolver($value, $directive);
        $method = $this->getResolverMethod($directive);
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
}
