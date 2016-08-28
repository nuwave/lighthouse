<?php

namespace Nuwave\Lighthouse\Support\Definition\Fields;

use Illuminate\Support\Fluent;

class EdgeField extends Fluent
{
    /**
     * Convert to GraphQL field.
     *
     * @return array
     */
    public function field()
    {
        return $this->toArray();
    }
}
