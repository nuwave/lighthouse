<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Closure;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Opis\Closure\SerializableClosure;

trait HandlesTypes
{
    /**
     * Check if schema still has packed types.
     *
     * @param array $types
     *
     * @return bool
     */
    protected function hasPackedTypes(array $types)
    {
        return collect($types)->reduce(function ($packed, $type) {
            return $packed ?: $this->isTypePacked($type);
        }, false);
    }

    /**
     * Check if type has been unpacked.
     *
     * @param Type $type
     *
     * @return bool
     */
    protected function isTypePacked(Type $type)
    {
        if (is_callable(array_get($type->config, 'fields'))) {
            return true;
        }

        return collect(array_get($type->config, 'fields'), [])->reduce(function ($packed, $field) {
            if ($packed) {
                return true;
            } elseif (is_callable(array_get($field, 'type'))) {
                return true;
            }

            $fieldType = array_get($field, 'type');
            if (method_exists($fieldType, 'getWrappedType')) {
                $wrappedType = $fieldType->getWrappedType();

                return is_callable($wrappedType);
            }

            return false;
        }, false);

        return false;
    }

    /**
     * Unpack type (fields and type).
     *
     * @param mixed $type
     *
     * @return mixed
     */
    protected function unpackType($type)
    {
        if (! isset($type->config['fields'])) {
            return $type;
        }

        $fields = $type->config['fields'];

        if ($fields instanceof SerializableClosure) {
            $closure = $fields->getClosure();
            $fields = $closure();
        } elseif (is_callable($fields)) {
            $fields = $fields();
        }

        $type->config['fields'] = collect($fields)->map(function ($field, $name) {
            $type = $field['type'];
            $resolve = $field['resolve'];

            if ($type instanceof SerializableClosure) {
                $closure = $type->getClosure();
                $type = $closure();
            } elseif (is_callable($type)) {
                $type = $type();
            }

            if ($resolve instanceof SerializableClosure) {
                $resolve = $resolve->getClosure();
            }

            $field['type'] = $type;
            $field['resolve'] = $resolve;

            return $field;
        })->toArray();

        return $type;
    }

    /**
     * Convert type for serialization.
     *
     * @param Type $type
     *
     * @return Type
     */
    protected function serializeableType(Type $type)
    {
        $config = $type->config;

        if (array_has($config, 'fields')) {
            $config['fields'] = collect($config['fields'])->mapWithKeys(function ($field, $key) {
                $field['type'] = $field['type'] instanceof Closure
                    ? new SerializableClosure($field['type'])
                    : $field['type'];

                if (array_has($field, 'resolve') && $field['resolve'] instanceof Closure) {
                    $field['resolve'] = new SerializableClosure($field['resolve']);
                }

                return [$key => $field];
            })->toArray();
        }

        $type->config = $config;

        return $type;
    }

    /**
     * Unpack field type.
     *
     * @param Type  $type
     * @param array $wrappers
     *
     * @return Type
     */
    protected function unpackFieldType($type, $wrappers = [])
    {
        if (method_exists($type, 'getWrappedType')) {
            return $this->unpackFieldType(
                $type->getWrappedType(),
                array_merge($wrappers, [get_class($type)])
            );
        }

        $unpackedType = is_callable($type) ? $type() : $type;

        return collect($wrappers)->reduce(function ($innerType, $type) {
            if (ListOfType::class == $type) {
                return Type::listOf($innerType);
            } elseif (NonNull::class == $type) {
                return Type::nonNull($innerType);
            } else {
                throw new \Exception("Unknown Type [{$type}]");
            }
        }, $unpackedType);
    }

    /**
     * Get fields for node.
     *
     * @param NodeValue $value
     *
     * @return array
     */
    protected function getFields(NodeValue $value)
    {
        $factory = $this->fieldFactory();

        return collect($value->getNodeFields())
            ->mapWithKeys(function ($field) use ($factory, $value) {
                $fieldValue = new FieldValue($value, $field);

                return [
                    $fieldValue->getFieldName() => $factory->handle($fieldValue),
                ];
            })->toArray();
    }

    /**
     * Get instance of field factory.
     *
     * @return FieldFactory
     */
    protected function fieldFactory()
    {
        return app(FieldFactory::class);
    }
}
