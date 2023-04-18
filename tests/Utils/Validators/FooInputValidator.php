<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class FooInputValidator extends Validator
{
    /** @return array{email: array<string>} */
    public function rules(): array
    {
        return [
            'email' => ['email'],
        ];
    }
}
