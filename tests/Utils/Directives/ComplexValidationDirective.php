<?php

namespace Tests\Utils\Directives;

use Illuminate\Contracts\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use Nuwave\Lighthouse\Support\Contracts\ArgValidationDirective;

class ComplexValidationDirective extends BaseDirective implements ArgValidationDirective
{
    use HasResolverArguments;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'complexValidation';
    }

    /**
     * @return mixed[]
     */
    public function getRules(): array
    {
        return [
            'id' => ['required'],
            'name' => ['sometimes', Rule::unique('users', 'name')->ignore($this->args['id'], 'id')],
        ];
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return [];
    }
}
