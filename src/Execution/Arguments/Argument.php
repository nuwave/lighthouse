<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Collection;

class Argument
{
    /**
     * The value given by the client.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>|mixed|array<mixed>
     */
    public $value;

    /**
     * The type of the argument.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType|null
     */
    public $type;

    /**
     * A list of directives associated with that argument.
     *
     * @var \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public $directives;

    /**
     * An argument may have a resolver that handles it's given value.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\ArgResolver|null
     */
    public $resolver;

    public function __construct()
    {
        $this->directives = new Collection();
    }

    /**
     * Get the plain PHP value of this argument.
     *
     * @return mixed the plain PHP value
     */
    public function toPlain()
    {
        return static::toPlainRecursive($this->value);
    }

    public function namedType(): ?NamedType
    {
        return static::namedTypeRecursive($this->type);
    }

    /**
     * @param \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType|null $type
     */
    protected static function namedTypeRecursive($type): ?NamedType
    {
        if ($type instanceof ListType) {
            return static::namedTypeRecursive($type->type);
        }

        return $type;
    }

    /**
     * Convert the given value to plain PHP values recursively.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>|mixed|array<mixed>  $value
     *
     * @return mixed|array<mixed>
     */
    protected static function toPlainRecursive($value)
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
