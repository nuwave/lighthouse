<?php


namespace Tests\Utils\Validators;


use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputValidator;
use Tests\Utils\Models\Company;

class UpdateCompanyInputValidator extends InputValidator
{
    public function rules(): array
    {
        $company = $this->model(Company::class);

        return [
            'name' => [Rule::unique('companies', 'name')->ignore($company)],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
