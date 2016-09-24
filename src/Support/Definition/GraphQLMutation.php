<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Interfaces\RelayMutation;

class GraphQLMutation extends GraphQLField
{
    /**
     * Mutation id sent from client.
     *
     * @var integer|null
     */
    protected $clientMutationId = null;

    /**
     * Generate Relay compliant output type.
     *
     * @return InputObjectType
     */
    public function type()
    {
        if (! $this instanceof RelayMutation) {
            return parent::type();
        }

        return new ObjectType([
            'name' => ucfirst($this->attributes['name']) . 'Payload',
            'fields' => array_merge($this->outputFields(), [
                    'clientMutationId' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => function () {
                            return $this->clientMutationId;
                        }
                    ]
                ])
        ]);
    }

    /**
     * Get the attributes of the field.
     *
     * @return array
     */
    public function getAttributes()
    {
        if (! $this instanceof RelayMutation) {
            return parent::getAttributes();
        }

        $attributes = array_merge($this->attributes, [
            'args' => $this->relayArgs()
        ], $this->attributes());

        $attributes['type'] = $this->type();
        $attributes['resolve'] = $this->getResolver();

        return $attributes;
    }

    /**
     * Get list of relay arguments.
     *
     * @return array
     */
    protected function relayArgs()
    {
        $inputType = new InputObjectType([
            'name' => ucfirst($this->attributes['name'].'Input'),
            'fields' => array_merge($this->args(), [
                'clientMutationId' => [
                    'type' => Type::nonNull(Type::string()),
                ]
            ])
        ]);

        return [
            'input' => [
                'type' => Type::nonNull($inputType)
            ]
        ];
    }

    /**
     * Resolve mutation.
     *
     * @param  mixed $_
     * @param  array $args
     * @param  mixed $context
     * @param  ResolveInfo $info
     * @return array
     */
    public function relayResolve($_, $args, $context, ResolveInfo $info)
    {
        if (isset($args['input']['id'])) {
            $args['input']['relay_id'] = $args['input']['id'];
            $args['input']['id'] = $this->decodeRelayId($args['input']['id']);
        }

        $this->clientMutationId = $args['input']['clientMutationId'];

        return $this->mutateAndGetPayload($args['input'], $context, $info);
    }

    /**
     * Get input for validation.
     *
     * @param  array  $arguments
     * @return array
     */
    protected function getInput(array $arguments)
    {
        return array_get($arguments, '1.input', []);
    }
}
