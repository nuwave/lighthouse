<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

class DropArgsDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply the @drop directives on the incoming arguments.
"""
directive @dropArgs on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->drop($argumentSet));
    }

    protected function drop(ArgumentSet &$argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $name => $argument) {
            $maybeDropDirective = $argument->directives->first(static fn (Directive $directive): bool => $directive instanceof DropDirective);

            if ($maybeDropDirective instanceof DropDirective) {
                unset($argumentSet->arguments[$name]);
            } else {
                // Recursively remove nested inputs using @drop directive.
                // We look for further ArgumentSet instances, they
                // might be contained within an array.
                Utils::applyEach(
                    function ($value): void {
                        if ($value instanceof ArgumentSet) {
                            $this->drop($value);
                        }
                    },
                    $argument->value,
                );
            }
        }

        return $argumentSet;
    }
}
