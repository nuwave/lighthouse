<?php


namespace Nuwave\Lighthouse\Schema;


use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Types\Field;

class ResolveInfo
{
    protected $field;

    protected $runAfter;

    protected $result;

    /**
     * ResolveInfo constructor.
     *
     * @param Field $field
     */
    public function __construct(Field $field)
    {
        $this->field = $field;
        $this->runAfter = collect();
    }

    public function field() : Field
    {
        return $this->field;
    }

    /**
     * @param null $result
     * @return Arrayable|null|string
     */
    public function result($result = null)
    {
        if(!is_null($result)) {
            $this->result = $result;
        }

        return $this->result;
    }

    /**
     * Runs the runAfters
     */
    public function runAfters()
    {
        $this->runAfter->each(function (callable $callable) {
            $callable($this->result());
        });
    }

    public function addAfter(callable $callable)
    {
        $this->runAfter->push($callable);
    }
}
