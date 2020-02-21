<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Utils;

class RulesGatherer
{
    /**
     * The gathered rules.
     *
     * @var array<string, mixed>
     */
    protected $rules;

    /**
     * The gathered messages.
     *
     * @var array<string, mixed>
     */
    protected $messages;

    public function gather(ArgumentSet $argumentSet)
    {
        $this->gatherRulesRecursively($argumentSet, new ArgumentPath());
    }

    public function gatherRulesRecursively(ArgumentSet $argumentSet, ArgumentPath $argumentPath)
    {
        foreach($argumentSet->directives as $directive) {
            if($directive instanceof ProvidesRules) {
                if(Utils::classUsesTrait($directive, )

                $this->extractRulesAndMessages($directive, $argumentPath);
            }
        }

        foreach($argumentSet->arguments as $name => $argument) {
            foreach()
        }
    }

    public function extractRulesAndMessages(ProvidesRules $providesRules, array $argumentPath)
    {
        $this->rules += $this->wrap($providesRules->rules(), $argumentPath);
        $this->messages += $this->wrap($providesRules->messages(), $argumentPath);
    }

    protected function wrap(array $rulesOrMessages, array $path)
    {
        $pathDotNotation = implode('.', $path);
        $withPath = [];

        foreach($rulesOrMessages as $key => $value) {
            $withPath["$pathDotNotation.$key"] = $value;
        }

        return $withPath;
    }
}
