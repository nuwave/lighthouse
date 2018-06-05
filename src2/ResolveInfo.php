<?php


namespace Nuwave\Lighthouse;


use Nuwave\Lighthouse\Types\Field;

class ResolveInfo
{
    protected $field;

    /**
     * ResolveInfo constructor.
     *
     * @param Field $field
     */
    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    public function field() : Field
    {
        return $this->field;
    }
}