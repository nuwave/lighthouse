<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class WithGlobalIdValidator extends Validator
{
    /** @return array{id: array<string>} */
    public function rules(): array
    {
        return [
            'id' => ['array'],
        ];
    }
}
