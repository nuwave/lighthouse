<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CountDirective extends BaseDirective implements FieldResolver, DefinedDirective
{

    /**
     * Name of the directive as used in the schema.
     *
     * @return string
     */
    public function name()
    {
        return 'count';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Resolve the field through a count.
"""
directive @count(
  """
  The relationship which you want to run the count on.
  """
  relation: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Makes a simple count query for the relation.
     *
     * @param FieldValue $value
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        return $value->setResolver(
            function (Model $model, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                $relation = $this->directiveArgValue('relation', $this->definitionNode->name->value);

                return $model->{$relation}()->count();
            }
        );
    }
}
