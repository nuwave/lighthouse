<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class BarRequiredWithoutFooValidator extends Validator
{
    /** @return array{bar: array<string>} */
    public function rules(): array
    {
        return [
            'bar' => ['required_without:foo'],
        ];
    }
}
