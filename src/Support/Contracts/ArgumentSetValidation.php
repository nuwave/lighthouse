<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Provide validation details for a set of arguments.
 *
 * https://laravel.com/docs/validation
 */
interface ArgumentSetValidation
{
    /**
     * Specify validation rules for the arguments.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array;

    /**
     * Specify custom messages for the rules.
     *
     * The returned array must map field + rule names (dot notation) to messages, e.g.:
     * [
     *   'foo.email' => 'The foo must be an email',
     *   'foo.required' => 'Everybody needs a foo',
     *   'bar.required' => 'You must pass the bar',
     * ]
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Specify custom attribute names to use in the validation message.
     *
     * @return array<string, string>
     */
    public function attributes(): array;
}
