<?php declare(strict_types=1);

namespace Tests\Utils\Rules;

use Illuminate\Contracts\Validation\Rule;
use Nuwave\Lighthouse\Support\Contracts\WithReferenceRule;

final class EqualFieldRule implements Rule, WithReferenceRule
{
    private string $argumentPath;

    public function passes($attribute, $value): bool
    {
        return false;
    }

    public function message(): string
    {
        return $this->argumentPath;
    }

    public function setArgumentPath(array $argumentPath): void
    {
        $this->argumentPath = implode('.', $argumentPath);
    }
}
