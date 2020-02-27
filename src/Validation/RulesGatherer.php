<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ListType;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
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

    public function __construct(ArgumentSet $argumentSet)
    {
        $this->gatherRulesRecursively($argumentSet, []);
    }

    public function gatherRulesRecursively(ArgumentSet $argumentSet, array $argumentPath)
    {
        $this->gatherRulesFromProviders($argumentSet, $argumentSet->directives, $argumentPath);

        foreach ($argumentSet->arguments as $name => $argument) {
            $nestedPath = array_merge($argumentPath, [$name]);

            $directivesForArray = $argument->directives->filter(
                Utils::instanceofMatcher(ArgDirectiveForArray::class)
            );
            $this->gatherRulesFromProviders($argument, $directivesForArray, $nestedPath);

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

    public function gatherRulesFromProviders($value, Collection $directives, array $path)
    {
        foreach ($directives as $directive) {
            if ($directive instanceof ProvidesRules) {
                if (Utils::classUsesTrait($directive, HasArgumentValue::class)) {
                    /** @var HasArgumentValue $directive */
                    $directive->setArgumentValue($value);
                }

                $this->extractRulesAndMessages($directive, $path);
            }
        }
    }

    protected function handleArgumentValue($value, Collection $directives, array $path)
    {
        $this->gatherRulesFromProviders($value, $directives, $path);

        if ($value instanceof ArgumentSet) {
            $this->gatherRulesRecursively($value, $path);
        }
    }

    public function extractRulesAndMessages(ProvidesRules $providesRules, array $argumentPath)
    {
        $rules = $providesRules->rules();
        // We might be passed just the rules for a single field, without any
        // field names. In this case, we just add on the path. When we have an
        // associative array of rules, the path is prepended to every key.
        $this->rules += isset($rules[0])
            ? [$this->pathDotNotation($argumentPath) => $rules]
            : $this->wrap($rules, $argumentPath);

        $messages = $providesRules->messages();
        $this->messages += $this->wrap($messages, $argumentPath);
    }

    protected function wrap(array $rulesOrMessages, array $path)
    {
        $pathDotNotation = $this->pathDotNotation($path);
        $withPath = [];

        foreach ($rulesOrMessages as $key => $value) {
            $withPath["$pathDotNotation.$key"] = $value;
        }

        return $withPath;
    }

    protected function pathDotNotation(array $path): string
    {
        return implode('.', $path);
    }
}
