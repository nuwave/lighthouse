<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class UpdateUserValidationDirective extends ValidationDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'updateUserValidation';
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
        return [
            'name.unique' => 'The chosen username is not available',
        ];
    }
}
