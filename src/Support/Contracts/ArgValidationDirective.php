<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Run Laravel validation on an argument.
 *
 * https://laravel.com/docs/validation
 */
interface ArgValidationDirective extends ArgDirective
{
    /**
     * Return validation rules for this argument.
     *
     * @return array
     */
    public function getRules(): array;

    /**
     * Return custom messages for the rules.
     *
     * @return array
     */
    public function getMessages(): array;
}
