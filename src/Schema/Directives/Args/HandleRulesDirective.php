<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Traits\HasArgumentPath;

trait HandleRulesDirective
{
    use HasArgumentPath;

    /**
     * @return array
     */
    public function getRules(): array
    {
        $rules = $this->directiveArgValue('apply');

        return [$this->argumentPathAsDotNotation() => $rules];
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return collect($this->directiveArgValue('messages'))
            ->mapWithKeys(function (string $message, string $rule): array {
                $argumentPath = $this->argumentPathAsDotNotation();

                return ["{$argumentPath}.{$rule}" => $message];
            })
            ->all();
    }
}
