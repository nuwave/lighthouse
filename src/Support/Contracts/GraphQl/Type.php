<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl;


use ArrayAccess;
use Illuminate\Support\Collection;

/**
 * Interface Type
 *
 * @package Nuwave\Lighthouse\Support\Contracts\GraphQl
 */
interface Type extends ArrayAccess
{
    /**
     * Returns or sets model
     *
     * @param null $model
     * @return string|static
     */
    public function model($model = null);

    /**
     * Returns or gets name
     *
     * @param $name
     * @return string|static
     */
    public function name($name = null);

    public function fields() : Collection;

    public function field($name) : Field;

    public function config() : Config;

    public function toGraphQlType();
}