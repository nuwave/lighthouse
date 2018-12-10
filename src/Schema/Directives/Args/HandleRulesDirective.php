<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Traits\HasArgumentPath;

trait HandleRulesDirective
{
    use HasArgumentPath;

    public function getRules(): array
    {
        $rules = $this->directiveArgValue('apply');

        return [$this->argumentPathAsDotNotation() => $rules];
    }

    public function getMessages(): array
    {
        return collect((array) $this->directiveArgValue('messages'))
            ->mapWithKeys(function ($message, $rule) {
                $prefix = $this->argumentPathAsDotNotation();

                return ["{$prefix}.{$rule}" => $message];
            })
            ->all();
    }
}
