<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class Argument
{
    /**
     * The value given by the client.
     *
     * @var ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>|mixed|array<mixed>
     */
    public mixed $value;

    /**
     * The type of the argument.
     */
    public ListType|NamedType $type;

    /**
     * A list of directives associated with that argument.
     *
     * @var \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public Collection $directives;

    /** An argument may have a resolver that handles its given value. */
    public ?ArgResolver $resolver = null;

    public function __construct()
    {
        $this->directives = new Collection();
    }

    /** Get the plain PHP value of this argument. */
    public function toPlain(): mixed
    {
        return static::toPlainRecursive($this->value);
    }

    public function namedType(): ?NamedType
    {
        return static::namedTypeRecursive($this->type);
    }

    protected static function namedTypeRecursive(ListType|NamedType|null $type): ?NamedType
    {
        if ($type instanceof ListType) {
            return static::namedTypeRecursive($type->type);
        }

        return $type;
    }

    /**
     * Convert the given value to plain PHP values recursively.
     *
     * @param  ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>|mixed|array<mixed>  $value
     *
     * @return mixed|array<mixed>
     */
    protected static function toPlainRecursive(mixed $value): mixed
    {
        if ($value instanceof ArgumentSet) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map([static::class, 'toPlainRecursive'], $value);
        }

        return $value;
    }
}
