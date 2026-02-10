<?php declare(strict_types=1);

namespace Tests\Utils\Validators\Query;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class MacroUserValidator extends Validator
{
    /** @return array{id: array<\Illuminate\Validation\Rules\Exists|string>} */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('name', 'Admin'),
            ],
        ];
    }
}
