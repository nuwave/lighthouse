<?php

namespace Tests\Unit\Support\Validator;

use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Nuwave\Lighthouse\Support\Validator\Validator;
use Tests\TestCase;

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
        $typeValue = new TypeValue($typeDefinition);
        $fieldValue = new FieldValue($typeDefinition->fields[0], $typeValue);

        $fieldValue->setResolver(function () {
            return 'foo';
        });

        (new ValidateDirective())->handleField($fieldValue);

        $this->expectException(ValidationError::class);
        $fieldValue->getResolver()(
            null,
            ['bar' => 'foo', 'baz' => 1]
        );
    }
}
