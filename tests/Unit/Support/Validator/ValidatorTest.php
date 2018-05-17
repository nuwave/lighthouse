<?php

namespace Tests\Unit\Support\Validator;

use Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Exceptions\ValidationError;
use Nuwave\Lighthouse\Support\Pipeline;
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

        $field = $this->getNodeField('
            type Mutation {
                foo(bar: String baz: Int): String @validate(validator: "foo.validator")
            }
            ')->setResolver(function () {
            return 'foo';
        });

        app(Pipeline::class)
            ->send($field)
            ->through([new ValidateDirective()])
            ->via('handleField')
            ->then(function($field) {
                return $field;
            });

        $this->expectException(ValidationError::class);
        $field->getResolver()(null, ['bar' => 'foo', 'baz' => 1]);
    }
}
