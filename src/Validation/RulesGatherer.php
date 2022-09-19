<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationRuleParser;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ListType;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation;
use Nuwave\Lighthouse\Support\Contracts\ArgumentValidation;
use Nuwave\Lighthouse\Support\Contracts\WithReferenceRule;
use Nuwave\Lighthouse\Support\Traits\HasArgumentValue;
use Nuwave\Lighthouse\Support\Utils;
use Throwable;

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
    protected function gatherRulesRecursively(ArgumentSet $argumentSet, array $argumentPath): void
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
    protected function gatherRulesForArgumentSet(ArgumentSet $argumentSet, Collection $directives, array $path): void
    {
        foreach ($directives as $directive) {
            if ($directive instanceof ArgumentSetValidation) {
                if (Utils::classUsesTrait($directive, HasArgumentValue::class)) {
                    assert(method_exists($directive, 'setArgumentValue'));
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
    protected function gatherRulesForArgument($value, Collection $directives, array $path): void
    {
        foreach ($directives as $directive) {
            if ($directive instanceof ArgumentValidation) {
                if (Utils::classUsesTrait($directive, HasArgumentValue::class)) {
                    assert(method_exists($directive, 'setArgumentValue'));
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
    protected function extractValidationForArgumentSet(ArgumentSetValidation $directive, array $argumentPath): void
    {
        $qualifiedRulesList = array_map(
            function (array $rules) use ($argumentPath): array {
                return $this->qualifyArgumentReferences($rules, $argumentPath);
            },
            $directive->rules()
        );

        $this->rules = array_merge_recursive(
            $this->rules,
            $this->wrap($qualifiedRulesList, $argumentPath)
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
    protected function extractValidationForArgument(ArgumentValidation $directive, array $argumentPath): void
    {
        $qualifiedRules = $this->qualifyArgumentReferences(
            $directive->rules(),
            // The last element is the name of the argument the rule is defined upon.
            // We want the qualified path to start from the parent level.
            array_slice($argumentPath, 0, -1)
        );

        $this->rules = array_merge_recursive(
            $this->rules,
            [implode('.', $argumentPath) => $qualifiedRules]
        );

        $this->messages += $this->wrap($directive->messages(), $argumentPath);

        $attribute = $directive->attribute();
        if (null !== $attribute) {
            $this->attributes = array_merge(
                $this->attributes,
                [implode('.', $argumentPath) => $attribute]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rulesOrMessages
     * @param  array<int|string>  $path
     *
     * @return array<string, mixed>
     */
    protected function wrap(array $rulesOrMessages, array $path): array
    {
        $withPath = [];

        foreach ($rulesOrMessages as $key => $value) {
            $combinedPath = implode('.', array_merge($path, [$key]));

            $withPath[$combinedPath] = $value;
        }

        return $withPath;
    }

    /**
     * Prepend rule arguments that refer to other arguments with the full path.
     *
     * This may be necessary to allow certain rules to be reusable when placed
     * upon input arguments. For example, `required_with:foo` may be defined
     * on an input value that is nested within the arguments under `input.0`.
     * It is thus changed to the full reference `required_with:input.0.foo`.
     *
     * @param  array<int, mixed>  $rules
     * @param  array<int|string>  $argumentPath
     *
     * @return array<int, object|string>
     */
    protected function qualifyArgumentReferences(array $rules, array $argumentPath): array
    {
        foreach ($rules as &$rule) {
            if (is_object($rule)) {
                if ($rule instanceof WithReferenceRule) {
                    $rule->setArgumentPath($argumentPath);
                }
                continue;
            }

            /**
             * @var array{
             *   0: string,
             *   1: array<int, mixed>,
             * } $parsed
             */
            $parsed = ValidationRuleParser::parse($rule);

            $name = $parsed[0];
            $args = $parsed[1];

            if ('WithReference' === $name) {
                $indexes = explode('_', $args[1]);
                array_splice($args, 1, 1);
                foreach ($indexes as $index) {
                    // Skipping over the first index, which is the name
                    $index = (int) $index + 1;
                    $args[$index] = implode('.', array_merge($argumentPath, [$args[$index]]));
                }

                $parsed = ValidationRuleParser::parse($args);

                $name = $parsed[0];
                $args = $parsed[1];
            }

            // Those rule lists are a subset of https://github.com/illuminate/validation/blob/8079fd53dee983e7c52d1819ae3b98c71a64fbc0/Validator.php#L206-L236
            // using the docs to know which ones reference other fields: https://laravel.com/docs/8.x/validation#available-validation-rules
            // We do not handle the Exclude* rules, those mutate the input and are not supported.

            // Rules where the first argument is a field reference
            if (in_array($name, [
                'Different',
                'Gt',
                'Gte',
                'Lt',
                'Lte',
                'ProhibitedIf',
                'ProhibitedUnless',
                'RequiredIf',
                'RequiredUnless',
                'Same',
            ])) {
                $args[0] = implode('.', array_merge($argumentPath, [$args[0]]));
            }

            // Rules where all arguments are field references
            if (in_array($name, [
                'Prohibits',
                'RequiredWith',
                'RequiredWithAll',
                'RequiredWithout',
                'RequiredWithoutAll',
            ])) {
                $args = array_map(
                    static function (string $field) use ($argumentPath): string {
                        return implode('.', array_merge($argumentPath, [$field]));
                    },
                    $args
                );
            }

            // Rules where the first argument is a date or a field reference
            if (is_string($args[0] ?? null) && in_array($name, [
                'After',
                'AfterOrEqual',
                'Before',
                'BeforeOrEqual',
            ])) {
                try {
                    Carbon::parse($args[0]);
                } catch (Throwable $argumentIsNotADate) {
                    $args[0] = implode('.', array_merge($argumentPath, [$args[0]]));
                }
            }

            // Convert back to the Laravel rule definition style rule:arg1,arg2
            $rule = count($args) > 0
                ? $name . ':' . implode(',', $args)
                : $name;
        }

        return $rules;
    }
}
