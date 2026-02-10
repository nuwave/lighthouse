<?php declare(strict_types=1);

namespace Tests\Utils\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class SelfValidatingValidator extends Validator
{
    public function rules(): array
    {
        // This also tests the input is being passed
        return $this->args->toArray();
    }
}
