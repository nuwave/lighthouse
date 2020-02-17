<?php


namespace Tests\Utils\Validators;


use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputValidator;
use Tests\Utils\Models\User;

class UpdateUserInputValidator extends InputValidator
{
    public function rules(): array
    {
        $user = $this->model(User::class);

        return [
            'email' => [
                'email',
                Rule::unique('users', 'email')->ignore($user),
            ],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
