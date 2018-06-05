<?php


namespace Nuwave\Lighthouse\Support\Webonyx;


use Closure;
use Exception;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Config;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Field;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type as TypeInterface;

class Type implements TypeInterface
{
    /** @var InputObjectType */
    protected $type;

    protected $model;

    /**
     * Type constructor.
     *
     * @param $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }


    public function model($model = null)
    {
        if(is_null($model)) {
            return $this->model;
        }
        $this->model = $model;
        return $this;
    }

    public function name($name = null)
    {
        if(is_null($name)) {
            return optional($this->type)->name;
        }
        $this->type->name = $name;
        return $this;
    }

    public function field($name): Field
    {
        // TODO: Implement field() method.
    }


    public function toGraphQlType()
    {
        $type = $this->type;
        while (($type instanceof TypeInterface)) {
            $type = $type->type;
        }
        return $type;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return method_exists($this, $offset) && !is_null($this->$offset());
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset();
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset($value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->$offset(null);
    }

    public function fields(): Collection
    {
        throw new Exception("missing");
    }

    public function config(): Config
    {
        return new class($this) implements Config {
            public function __construct(TypeInterface $type)
            {
                $this->type = $type;
            }

            public function fields(Closure $fields = null)
            {
                if(is_null($fields)) {
                    return $this->type->toGraphQlType()->config['fields'];
                }

                $this->type->toGraphQlType()->config['fields'] = $fields;
                return $this;
            }
        };
    }
}