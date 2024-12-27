<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Bind\BindDirective;

use function is_array;

class BindingExists implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function __construct(
        private BindDirective $directive,
    ) {}

    public function setValidator(Validator $validator): self
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
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

            $this->validator?->addFailure("$attribute.$key", 'exists');
        }
    }
}
