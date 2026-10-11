<?php declare(strict_types=1);

namespace Tests\Utils\Validators\Query;

use Nuwave\Lighthouse\Validation\Validator;

final class FooValidator extends Validator
{
    /** @return array{email: array<string>} */
    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }
}
