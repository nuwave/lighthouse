<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind\Validation;

use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Bind\BindDirective;

class BindingExists implements InvokableRule, ValidatorAwareRule
{
    protected ?Validator $validator = null;

    public function __construct(
        protected BindDirective $directive,
    ) {}

    /**
     * Because of backwards compatibility with Laravel 9, typehints for this method
     * must be set through PHPDoc as the interface did not include typehints.
     *
     * @see https://laravel.com/docs/9.x/validation#using-rule-objects
     *
     * @param  string  $attribute
     *
     * @parent mixed $value
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function __invoke($attribute, $value, $fail): void
    {
        $binding = $this->directive->transform($value);

        if ($binding === null) {
            $fail('validation.exists')->translate();

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $scalarValue) {
            if ($binding->has($scalarValue)) {
                continue;
            }

            $this->validator?->addFailure("{$attribute}.{$key}", 'exists');
        }
    }

    /**
     * Because of backwards compatibility with Laravel 9, typehints for this method
     * must be set through PHPDoc as the interface did not include typehints.
     *
     * @see https://laravel.com/docs/9.x/validation#custom-validation-rules
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function setValidator($validator): self
    {
        $this->validator = $validator;

        return $this;
    }
}
