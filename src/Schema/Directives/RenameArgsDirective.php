<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class RenameArgsDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply the @rename directives on the incoming arguments.
"""
directive @renameArgs on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $fieldValue->addArgumentSetTransformer(function (ArgumentSet $argumentSet): ArgumentSet {
            return $argumentSet->rename();
        });

        return $next($fieldValue);
    }
}
