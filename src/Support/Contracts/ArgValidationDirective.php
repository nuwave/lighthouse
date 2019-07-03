<?php

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Run Laravel validation on field arguments.
 *
 * https://laravel.com/docs/validation
 */
interface ArgValidationDirective extends ArgDirective
{
    /**
     * Return validation rules for the arguments.
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
