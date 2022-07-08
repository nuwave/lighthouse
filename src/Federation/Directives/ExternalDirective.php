<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ExternalDirective extends BaseDirective implements FieldResolver
{
    public const NAME = 'external';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Individual federated services should be runnable without having the entire graph present. Fields marked with @external
are declarations of fields that are defined in another service. All fields referred to in @key, @requires, and @provides
directives need to have corresponding @external fields in the same service.

https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
"""
directive @external on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $defaultFieldResolver = Executor::getDefaultFieldResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($defaultFieldResolver) {
            // The parent might just hold a foreign key to the external object, in which case we just return that.
            return is_scalar($root)
                ? $root
                : $defaultFieldResolver($root, $args, $context, $resolveInfo);
        });

        return $fieldValue;
    }
}
