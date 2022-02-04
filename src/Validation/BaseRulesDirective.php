<?php

namespace Nuwave\Lighthouse\Validation;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgumentValidation;

abstract class BaseRulesDirective extends BaseDirective implements ArgumentValidation, ArgManipulator
{
    public function rules(): array
    {
        $rules = $this->directiveArgValue('apply');

        // Custom rules may be referenced through their fully qualified class name.
        // The Laravel validator expects a class instance to be passed, so we
        // resolve any given rule where a corresponding class exists.
        foreach ($rules as $key => $rule) {
            if (class_exists($rule)) {
                $rules[$key] = app($rule);
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = $this->directiveArgValue('messages');
        if (null === $messages) {
            return [];
        }

        if (isset($messages[0])) {
            /** @var array<string, string> $flattened */
            $flattened = [];

            /**
             * We know this holds true, because it has been validated before.
             *
             * @var array{rule: string, message: string} $messageMap
             */
            foreach ($messages as $messageMap) {
                $flattened[$messageMap['rule']] = $messageMap['message'];
            }

            return $flattened;
        }

        return $messages;
    }

    public function attribute(): ?string
    {
        return $this->directiveArgValue('attribute');
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ) {
        $this->validateRulesArg();
        $this->validateMessageArg();
    }

    protected function validateRulesArg(): void
    {
        $rules = $this->directiveArgValue('apply');

        if (! is_array($rules)) {
            $this->invalidApplyArgument($rules);
        }

        if (0 === count($rules)) {
            $this->invalidApplyArgument($rules);
        }

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                $this->invalidApplyArgument($rules);
            }
        }
    }

    protected function validateMessageArg(): void
    {
        $messages = $this->directiveArgValue('messages');
        if (null === $messages) {
            return;
        }

        if (! is_array($messages)) {
            $this->invalidMessageArgument($messages);
        }

        if (isset($messages[0])) {
            foreach ($messages as $messageMap) {
                if (! is_array($messageMap)) {
                    $this->invalidMessageArgument($messages);
                }

                $rule = $messageMap['rule'] ?? null;
                if (! is_string($rule)) {
                    $this->invalidMessageArgument($messages);
                }

                $message = $messageMap['message'] ?? null;
                if (! is_string($message)) {
                    $this->invalidMessageArgument($messages);
                }
            }
        } else {
            foreach ($messages as $rule => $message) {
                if (! is_string($rule)) {
                    $this->invalidMessageArgument($messages);
                }

                if (! is_string($message)) {
                    $this->invalidMessageArgument($messages);
                }
            }
        }
    }

    /**
     * @param  mixed  $messages  Whatever faulty value was given for messages
     *
     * @throws DefinitionException
     */
    protected function invalidMessageArgument($messages): void
    {
        $encoded = \Safe\json_encode($messages);
        throw new DefinitionException(
            "The `messages` argument of @`{$this->name()}` on `{$this->nodeName()} must be a list of input values with the string keys `rule` and `message`, got: {$encoded}"
        );
    }

    /**
     * @param  mixed  $apply  Any invalid value
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function invalidApplyArgument($apply): void
    {
        $encoded = \Safe\json_encode($apply);
        throw new DefinitionException(
            "The `apply` argument of @`{$this->name()}` on `{$this->nodeName()}` has to be a list of strings, got: {$encoded}"
        );
    }
}
