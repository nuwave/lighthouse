<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath as HasArgumentPathTrait;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;

class RulesForArrayDirective extends BaseDirective implements ArgValidationDirective, ArgDirectiveForArray, HasArgumentPathContract
{
    use HasArgumentPathTrait;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rulesForArray';
    }

    /**
     * @return mixed[]
     */
    public function getRules(): array
    {
        $rules = $this->directiveArgValue('apply');

        if (! in_array('array', $rules)) {
            $rules = Arr::prepend($rules, 'array');
        }

        return [$this->argumentPathAsDotNotation() => $rules];
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return (new Collection($this->directiveArgValue('messages')))
            ->mapWithKeys(function (string $message, string $rule): array {
                $argumentPath = $this->argumentPathAsDotNotation();

                return ["{$argumentPath}.{$rule}" => $message];
            })
            ->all();
    }
}
