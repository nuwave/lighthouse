<?php


namespace Nuwave\Lighthouse\Execution;


use Illuminate\Database\Eloquent\Model;

/**
 * Class InputTypeValidator
 */
abstract class InputTypeValidator
{

    /**
     * @var array
     */
    protected $input;

    /**
     * InputTypeValidator constructor.
     *
     * @param array $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    /**
     * @param string $key
     * @param null   $default
     *
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return data_get($this->input, $key, $default);
    }

    /**
     * Get an instance of the model this input type tries to update.
     *
     * @param string $modelClass
     *
     * @return Model
     */
    public function model(string $modelClass): ?Model
    {
        /** @var Model $model */
        $model = new $modelClass;

        return $modelClass::find($this->input($model->getKeyName()));
    }

    /**
     * @return array
     */
    abstract function rules(): array;

    /**
     * @return array
     */
    function messages(): array
    {
        return [];
    }
}
