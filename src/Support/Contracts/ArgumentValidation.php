<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Provide validation details for a single argument.
 *
 * https://laravel.com/docs/validation
 */
interface ArgumentValidation
{
    /**
     * Specify validation rules for the argument.
     *
     * @return array<int, mixed>
     */
    public function rules(): array;

    /**
     * Specify custom messages for the rules.
     *
     * The returned array must map rule names to messages, e.g.:
     * [
     *   'email' => 'Must be an email',
     *   'required' => 'Has to be there',
     * ]
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Specify a custom attribute name to use in the validation message.
     */
    public function attribute(): ?string;
}
