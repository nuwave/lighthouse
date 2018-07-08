<?php

namespace Tests\Unit\Support\Validator;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Validator\Validator;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective;

class ValidatorTest extends TestCase
{
    /**
     * @test
     */
    public function itCanWrapResolverWithValidation()
    {
        $this->app->bind('foo.validator', function ($app, $params) {
            return new class($params['root'], $params['args'], $params['context'], $params['info']) extends Validator {
                protected function rules()
                {
                    return [
                        'foo' => ['min:5'],
                        'bar' => ['min:5'],
                    ];
                }
            };
        });

        $typeDefinition = PartialParser::objectTypeDefinition('
            type Mutation {
                foo(bar: String baz: Int): String @validate(validator: "foo.validator")
            }
        ');

        $fieldDefinition = $typeDefinition->fields[0];
        $fieldValue = new FieldValue(new NodeValue($typeDefinition), $fieldDefinition);
        $fieldValue->setResolver(function () {
            return 'foo';
        });

        (new ValidateDirective())->hydrate($fieldDefinition)->handleField(
            $fieldValue,
            function ($fieldValue) {
                return $fieldValue;
            }
        );

        $this->expectException(ValidationError::class);
        $fieldValue->getResolver()(
            null,
            ['bar' => 'foo', 'baz' => 1]
        );
    }
}
