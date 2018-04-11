<?php

namespace Tests\Unit\Support\Validator;

use Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Nuwave\Lighthouse\Support\Validator\Validator;
use Tests\TestCase;

class ValidatorTest extends TestCase
{
    /**
     * @test
     * @group failing
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

        $document = $this->parse('
        type Mutation {
            foo(bar: String baz: Int): String @validate(validator: "foo.validator")
        }
        ');

        $node = new NodeValue($document->definitions[0]);
        $field = collect($node->getNodeFields())->map(function ($field) use ($node) {
            return (new FieldValue($node, $field))->setResolver(function () {
                return 'foo';
            });
        })->first();

        (new ValidateDirective())->handleField($field);

        $this->expectException(ValidationError::class);
        $field->getResolver()(null, ['bar' => 'foo', 'baz' => 1]);
    }
}
