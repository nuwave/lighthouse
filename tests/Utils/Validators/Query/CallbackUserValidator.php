<?php declare(strict_types=1);

namespace Tests\Utils\Validators\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class CallbackUserValidator extends Validator
{
    /** @return array{id: array<\Illuminate\Validation\Rules\Exists|string>} */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(static fn (Builder $query): Builder => $query->where('name', 'Admin')),
            ],
        ];
    }
}
