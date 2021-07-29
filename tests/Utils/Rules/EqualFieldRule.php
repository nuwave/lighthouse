<?php

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\WithReferenceRule;

class EqualFieldRule implements Rule, DataAwareRule, WithReferenceRule
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

        return $this->reference === $value;
    }

    public function message(): string
    {
        return "The $this->attribute must be equal to $this->field.";
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData($data): EqualFieldRule
    {
        $this->reference = Arr::get($data, $this->field);

        return $this;
    }

    public function setArgumentPath(string $argumentPath): void
    {
        $this->field = $argumentPath.'.'.$this->field;
    }
}
