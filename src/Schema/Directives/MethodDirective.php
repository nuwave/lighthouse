<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MethodDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Call a method with a given `name` on the class that represents a type to resolve a field.
Use this if the data is not accessible as an attribute (e.g. `$model->myData`) or if you
want to pass argument to the method.
"""
directive @method(
  """
  Specify the method of which to fetch the data from.
  """
  name: String

  """
  The field arguments to pass (in order) to the underlying method. Each string in the array
  should correspond to an argument of the field.
  """
  pass: [String!]
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        /** @var string $method */
        $method = $this->directiveArgValue(
            'name',
            $this->nodeName()
        );

        $paramsToBind = $this->directiveArgValue(
            'pass',
            $this->nodeName()
        );

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($method, $paramsToBind) {
                if (empty($paramsToBind)) {
                    return call_user_func([$root, $method], $root, $args, $context, $resolveInfo);
                }

                $parameters = array_map(function ($argument) use ($args) {
                    if (! isset($args[$argument])) {
                        throw new DirectiveException("No field argument for the pass element: $argument");
                    }

                    return $args[$argument];
                }, $paramsToBind);

                return call_user_func_array([$root, $method], $parameters);
            }
        );
    }
}
