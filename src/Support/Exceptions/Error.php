<?php


namespace Nuwave\Lighthouse\Support\Exceptions;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class Error implements Arrayable
{
    public $message;
    public $locations = null;
    public $custom = [];

    /**
     * Error constructor.
     *
     * @param $message
     * @param null $locations
     * @param array $custom
     */
    public function __construct($message, $locations = null, $custom = [])
    {
        $this->message = $message;
        $this->locations = $locations;
        $this->custom = $custom;
    }

    public static function default() : Error
    {
        return new Error("Random error encountered.");
    }

    public static function fromArray(array $data) : Error
    {
        if(!Arr::has($data, ['message', 'locations'])) {
            return Error::default();
        }
        return new Error(
            $data['message'],
            $data['locations'],
            Arr::except($data, ['message', 'locations'])
        );
    }


    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray() : array
    {
        return array_merge($this->custom, [
            'message' => $this->message,
            'locations' => $this->locations,
        ]);
    }
}