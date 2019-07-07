<?php

namespace Tests\Utils\Directives;

use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class FooValidationDirective extends ValidationDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'fooValidation';
    }

    /**
     * @return mixed[]
     */
    public function getRules(): array
    {
        return [
            'foo' => ['alpha'],
        ];
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return [];
    }
}
