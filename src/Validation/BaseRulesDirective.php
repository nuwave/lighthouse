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

        return array_map(
            static function (string $rule) {
                // Custom rules may be referenced through their fully qualified class name.
                // The Laravel validator expects a class instance to be passed, so we
                // resolve any given rule where a corresponding class exists.
                if (class_exists($rule)) {
                    return app($rule);
                }

                return $rule;
            },
            $rules
        );
    }

    public function messages(): array
    {
        $messages = $this->directiveArgValue('messages');
        if ($messages === null) {
            return [];
        }

        if (isset($messages[0])) {
            /** @var array<string, string> $flattened */
            $flattened = [];

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

        if (count($rules) === 0) {
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
        if ($messages === null) {
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
     * @param  mixed  $messages Whatever faulty value was given for messages
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
     * @param $rules
     * @throws DefinitionException
     * @throws \Safe\Exceptions\JsonException
     */
    protected function invalidApplyArgument($rules): void
    {
        $encoded = \Safe\json_encode($rules);
        throw new DefinitionException(
            "The `apply` argument of @`{$this->name()}` on `{$this->nodeName()}` has to be a list of strings, got: {$encoded}"
        );
    }
}
