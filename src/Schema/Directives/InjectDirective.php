<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class InjectDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Inject a value from the context object into the arguments.
"""
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
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $contextAttributeName = $this->directiveArgValue('context');
        if (! $contextAttributeName) {
            throw new DefinitionException("The `inject` directive on {$fieldValue->getParentName()} [{$fieldValue->getFieldName()}] must have a `context` argument");
        }

        $argumentName = $this->directiveArgValue('name');
        if (! $argumentName) {
            throw new DefinitionException("The `inject` directive on {$fieldValue->getParentName()} [{$fieldValue->getFieldName()}] must have a `name` argument");
        }

        $fieldValue->wrapResolver(static fn (callable $resolver): \Closure => static function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($contextAttributeName, $argumentName, $resolver) {
            $valueFromContext = data_get($context, $contextAttributeName);
            $argumentSet = $resolveInfo->argumentSet;
            $argumentSet->addValue($argumentName, $valueFromContext);

            return $resolver(
                $root,
                $argumentSet->toArray(),
                $context,
                $resolveInfo,
            );
        });
    }
}
