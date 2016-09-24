<?php

namespace Nuwave\Relay\Support\Definition;

use Validator;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;

abstract class RelayMutation extends GraphQLMutation
{
    /**
     * Type being mutated is RelayType.
     *
     * @var boolean
     */
    protected $mutatesRelayType = true;

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
        return new ObjectType([
            'name' => ucfirst($this->name()) . 'Payload',
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
     * Generate Relay compliant arguments.
     *
     * @return array
     */
    public function args()
    {
        $inputType = new InputObjectType([
            'name' => ucfirst($this->name()) . 'Input',
            'fields' => array_merge($this->inputFields(), [
                'clientMutationId' => [
                    'type' => Type::nonNull(Type::string())
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
     * @param  mixed       $_
     * @param  array       $args
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @return array
     */
    public function resolve($_, $args, $context, ResolveInfo $info)
    {
        if ($this->mutatesRelayType && isset($args['input']['id'])) {
            $args['input']['relay_id'] = $args['input']['id'];
            $args['input']['id'] = $this->decodeRelayId($args['input']['id']);
        }

        $this->clientMutationId = $args['input']['clientMutationId'];

        return $this->mutateAndGetPayload($args['input'], $info);
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

    /**
     * Perform mutation.
     *
     * @param  array       $input
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @return array
     */
    abstract protected function mutateAndGetPayload(array $input, $context, ResolveInfo $info);

    /**
     * List of available input fields.
     *
     * @return array
     */
    abstract protected function inputFields();

    /**
     * List of output fields.
     *
     * @return array
     */
    abstract protected function outputFields();

    /**
     * Get name of mutation.
     *
     * @return string
     */
    abstract protected function name();
}
