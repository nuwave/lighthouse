<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class Argument
{
    /**
     * The value given by the client.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet[]|mixed|mixed[]
     */
    public $value;

    /**
     * The type of the argument.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType
     */
    public $type;

    /**
     * A list of directives associated with that argument.
     *
     * @var \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public $directives;

    /**
     * Get the plain PHP value of this argument.
     *
     * @return mixed
     */
    public function toPlain()
    {
        return static::toPlainRecursive($this->value);
    }

    /**
     * Convert the given value to plain PHP values recursively.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet[]|mixed|mixed[]  $value
     * @return mixed|mixed[]
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
