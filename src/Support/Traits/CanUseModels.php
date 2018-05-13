<?php


namespace Nuwave\Lighthouse\Support\Traits;


use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

trait CanUseModels
{
    public abstract function name();

    /**
     * @param FieldValue $value
     * @return mixed|string
     * @throws DirectiveException
     */
    public function getModelClass(FieldValue $value) {
        $model = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'model'
        );

        if (! $model) {
            $message = sprintf(
                'A `model` argument must be assigned to the %s directive on the %s field [%s]',
                $this->name(),
                $value->getNodeName(),
                $value->getFieldName()
            );

            throw new DirectiveException($message);
        }

        if (! class_exists($model)) {
            $model = config('lighthouse.namespaces.models').'\\'.$model;
        }

        return $model;
    }
}