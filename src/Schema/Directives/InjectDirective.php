<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class InjectDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'inject';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
directive @inject(      
  """
  A path to the property of the context that will be injected.
  If the value is nested within the context, you may use dot notation
  to get it, e.g. "user.id".
  """
  context: String!

  """
  The target name of the argument into which the value is injected.
  You can use dot notation to set the value at arbitrary depth
  within the incoming argument.
  """
  name: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $contextAttributeName = $this->directiveArgValue('context');
        if (! $contextAttributeName) {
            throw new DirectiveException(
                "The `inject` directive on {$fieldValue->getParentName()} [{$fieldValue->getFieldName()}] must have a `context` argument"
            );
        }

        $argumentName = $this->directiveArgValue('name');
        if (! $argumentName) {
            throw new DirectiveException(
                "The `inject` directive on {$fieldValue->getParentName()} [{$fieldValue->getFieldName()}] must have a `name` argument"
            );
        }

        $previousResolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($contextAttributeName, $argumentName, $previousResolver) {
                    return $previousResolver(
                        $rootValue,
                        Arr::add($args, $argumentName, data_get($context, $contextAttributeName)),
                        $context,
                        $resolveInfo
                    );
                }
            )
        );
    }
}
