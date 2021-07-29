<?php

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;
use Nuwave\Lighthouse\Support\Contracts\WithReferenceRule;

class EqualFieldRule implements Rule, WithReferenceRule
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $attribute;

    /**
     * @var int
     */
    protected $reference;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    public function passes($attribute, $value): bool
    {
        $this->attribute = $attribute;

        return false;
    }

    public function message(): string
    {
        return "The $this->attribute must be equal to $this->field.";
    }

    public function setArgumentPath(string $argumentPath): void
    {
        $this->field = $argumentPath.'.'.$this->field;
    }
}
