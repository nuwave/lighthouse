<?php

namespace Tests\Utils\Validators\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class CallbackUserValidator extends Validator
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(function (Builder $query): void {
                        $query->where('name', 'Admin');
                    }),
            ],
        ];
    }
}
