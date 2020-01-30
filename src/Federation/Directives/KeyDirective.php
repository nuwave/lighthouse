<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class KeyDirective extends BaseDirective
{
    /**
     * @return string
     * @see https://www.apollographql.com/docs/apollo-server/federation/federation-spec/#schema-modifications-glossary
     */
    public static function definition(): string
    {
        return /* @lang GraphQL */
            <<<'SDL'
"""
The @key directive is used to indicate a combination of fields that can be used to uniquely identify and fetch an object
or interface. Multiple keys can be defined on a single object type:

    type User @key(fields: "id") @key(fields: "another_field") @extends {
"""
directive @key(
    """
    Fields that can be used to uniquely identify and fetch an object or interface
    """
    fields: _FieldSet!
) on OBJECT | INTERFACE
SDL;
    }

    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'key';
    }
}
