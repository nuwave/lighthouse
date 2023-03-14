<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

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

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->rename($argumentSet));
    }

    protected function rename(ArgumentSet &$argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $name => $argument) {
            // Recursively apply the renaming to nested inputs.
            // We look for further ArgumentSet instances, they
            // might be contained within an array.
            Utils::applyEach(
                function ($value): void {
                    if ($value instanceof ArgumentSet) {
                        $this->rename($value);
                    }
                },
                $argument->value,
            );

            $maybeRenameDirective = $argument->directives->first(static fn (Directive $directive): bool => $directive instanceof RenameDirective);

            if ($maybeRenameDirective instanceof RenameDirective) {
                $argumentSet->arguments[$maybeRenameDirective->attributeArgValue()] = $argument;
                unset($argumentSet->arguments[$name]);
            }
        }

        return $argumentSet;
    }
}
