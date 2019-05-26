<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;
use Nuwave\Lighthouse\Support\Traits\HasArgumentPath as HasArgumentPathTrait;
use Nuwave\Lighthouse\Support\Contracts\HasArgumentPath as HasArgumentPathContract;

class RulesDirective extends BaseDirective implements ArgValidationDirective, HasArgumentPathContract
{
    use HasArgumentPathTrait;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rules';
    }

    /**
     * @return mixed[]
     */
    public function getRules(): array
    {
        $rules = $this->directiveArgValue('apply');

        // Custom rules may be referenced through their fully qualified class name.
        // The Laravel validator expects a class instance to be passed, so we
        // resolve any given rule where a corresponding class exists.
        foreach ($rules as $key => $rule) {
            if (class_exists($rule)) {
                $rules[$key] = resolve($rule);
            }
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
