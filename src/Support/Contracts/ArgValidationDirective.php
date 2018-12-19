<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgValidationDirective extends ArgDirective
{
    /**
     * @return array
     */
    public function getMessages(): array;

    /**
     * @return array
     */
    public function getRules(): array;
}
