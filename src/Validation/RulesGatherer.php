<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ListType;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation;
use Nuwave\Lighthouse\Support\Contracts\ArgumentValidation;
use Nuwave\Lighthouse\Support\Traits\HasArgumentValue;
use Nuwave\Lighthouse\Support\Utils;

class RulesGatherer
{
    /**
     * The gathered rules.
     *
     * @var array<string, mixed>
     */
    public $rules = [];

    /**
     * The gathered messages.
     *
     * @var array<string, mixed>
     */
    public $messages = [];

    /**
     * The gathered attributes.
     *
     * @var array<string, string>
     */
    public $attributes = [];

    public function __construct(ArgumentSet $argumentSet)
    {
        $this->gatherRulesRecursively($argumentSet, []);
    }

    /**
     * @param  array<int|string>  $argumentPath
     */
    public function gatherRulesRecursively(ArgumentSet $argumentSet, array $argumentPath): void
    {
        $this->gatherRulesForArgumentSet($argumentSet, $argumentSet->directives, $argumentPath);

        $argumentsWithUndefined = $argumentSet->argumentsWithUndefined();
        foreach ($argumentsWithUndefined as $name => $argument) {
            $nestedPath = array_merge($argumentPath, [$name]);

            $directivesForArray = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirectiveForArray::class)
            );
            $this->gatherRulesForArgument($argument, $directivesForArray, $nestedPath);

            $directivesForArgument = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirective::class)
            );

            if (
                $argument->type instanceof ListType
                && is_array($argument->value)
            ) {
                foreach ($argument->value as $index => $value) {
                    $this->handleArgumentValue($value, $directivesForArgument, array_merge($nestedPath, [$index]));
                }
            } else {
                $this->handleArgumentValue($argument->value, $directivesForArgument, $nestedPath);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  array<int|string>  $path
     */
    public function gatherRulesForArgumentSet(ArgumentSet $argumentSet, Collection $directives, array $path): void
    {
        foreach ($directives as $directive) {
            if ($directive instanceof ArgumentSetValidation) {
                if (Utils::classUsesTrait($directive, HasArgumentValue::class)) {
                    /** @var \Nuwave\Lighthouse\Support\Contracts\Directive&\Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation&\Nuwave\Lighthouse\Support\Traits\HasArgumentValue $directive */
                    // @phpstan-ignore-next-line using trait in typehint
                    $directive->setArgumentValue($argumentSet);
                }

                $this->extractValidationForArgumentSet($directive, $path);
            }
        }
    }

    /**
     * @param  mixed  $value  Any argument value is possible
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  array<int|string>  $path
     */
    public function gatherRulesForArgument($value, Collection $directives, array $path): void
    {
        foreach ($directives as $directive) {
            if ($directive instanceof ArgumentValidation) {
                if (Utils::classUsesTrait($directive, HasArgumentValue::class)) {
                    /** @var \Nuwave\Lighthouse\Support\Contracts\Directive&\Nuwave\Lighthouse\Support\Contracts\ArgumentValidation&\Nuwave\Lighthouse\Support\Traits\HasArgumentValue $directive */
                    // @phpstan-ignore-next-line using trait in typehint
                    $directive->setArgumentValue($value);
                }

                $this->extractValidationForArgument($directive, $path);
            }
        }
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\Argument|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|mixed  $value
     * @param  \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directives
     * @param  array<int|string>  $path
     */
    protected function handleArgumentValue($value, Collection $directives, array $path): void
    {
        $this->gatherRulesForArgument($value, $directives, $path);

        if ($value instanceof ArgumentSet) {
            $this->gatherRulesRecursively($value, $path);
        }
    }

    /**
     * @param  array<int|string>  $argumentPath
     */
    public function extractValidationForArgumentSet(ArgumentSetValidation $directive, array $argumentPath): void
    {
        $this->rules = array_merge_recursive(
            $this->rules,
            $this->wrap($directive->rules(), $argumentPath)
        );

        $this->messages += $this->wrap($directive->messages(), $argumentPath);

        $this->attributes = array_merge(
            $this->attributes,
            $this->wrap($directive->attributes(), $argumentPath)
        );
    }

    /**
     * @param  array<int|string>  $argumentPath
     */
    public function extractValidationForArgument(ArgumentValidation $directive, array $argumentPath): void
    {
        $this->rules = array_merge_recursive(
            $this->rules,
            [$this->pathDotNotation($argumentPath) => $directive->rules()]
        );

        $this->messages += $this->wrap($directive->messages(), $argumentPath);

        $attribute = $directive->attribute();
        if (null !== $attribute) {
            $this->attributes = array_merge(
                $this->attributes,
                [$this->pathDotNotation($argumentPath) => $attribute]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rulesOrMessages
     * @param  array<int|string>  $path
     * @return  array<string, mixed>
     */
    protected function wrap(array $rulesOrMessages, array $path): array
    {
        $withPath = [];

        foreach ($rulesOrMessages as $key => $value) {
            $combinedPath = array_merge($path, [$key]);
            $pathDotNotation = $this->pathDotNotation($combinedPath);

            $withPath[$pathDotNotation] = $value;
        }

        return $withPath;
    }

    /**
     * @param  array<int|string>  $path
     */
    protected function pathDotNotation(array $path): string
    {
        return implode('.', $path);
    }
}
