<?php

namespace Tests\Utils\Validators;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class GetFaultyUserValidator extends Validator
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                return $query->where('name', '=', 'Admin')
                    ->whereNull('created_at');
            })],
        ];
    }
}
