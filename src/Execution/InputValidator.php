<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

abstract class InputValidator
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $input;

    /**
     * @return array
     */
    abstract public function rules(): array;

    /**
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    public function setInput(ArgumentSet $input): void
    {
        $this->input = $input;
    }
}
