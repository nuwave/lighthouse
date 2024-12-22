<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

use function class_exists;
use function is_callable;
use function is_subclass_of;
use function sprintf;

/**
 * @template TClass
 * @property-read class-string<TClass> $class
 * @property-read string $column
 * @property-read array<string> $with
 * @property-read bool $optional
 */
class BindDefinition
{
    /**
     * @param class-string<TClass> $class
     * @param array<string> $with
     */
    public function __construct(
        public string $class,
        public string $column,
        public array $with,
        public bool $optional,
    ) {}

    /**
     * @param array<string, string> $exceptionMessagePlaceholders
     */
    public function validate(array $exceptionMessagePlaceholders): void
    {
        if (! class_exists($this->class)) {
            throw new DefinitionException(sprintf(
                "@bind argument `class` defined on %s of %s must be an existing class, received `$this->class`.",
                ...$this->formatExceptionMessagePlaceholders($exceptionMessagePlaceholders),
            ));
        }

        if ($this->isModelBinding()) {
            return;
        }

        if (is_callable($this->class)) {
            return;
        }

        throw new DefinitionException(sprintf(
            "@bind argument `class` defined on %s of %s must be an Eloquent" .
            "model or a callable class, received `$this->class`.",
            ...$this->formatExceptionMessagePlaceholders($exceptionMessagePlaceholders),
        ));
    }

    /**
     * @param array<string, string> $placeholders
     * @return array<int, string>
     */
    private function formatExceptionMessagePlaceholders(array $placeholders): array
    {
        return Collection::make($placeholders)
            ->map(fn (string $value, string $key): string => "$key `$value`")
            ->values()
            ->all();
    }

    public function isModelBinding(): bool
    {
        return is_subclass_of($this->class, Model::class);
    }
}
