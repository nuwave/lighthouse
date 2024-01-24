<?php declare(strict_types=1);

namespace Tests\Unit\Scout;

use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
use Nuwave\Lighthouse\Scout\ScoutEnhancer;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class ScoutEnhancerTest extends TestCase
{
    public function testEnhanceBuilder(): void
    {
        $directiveOne = new class() implements Directive, ScoutBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
            {
                return $builder->where('one', $value);
            }
        };
        $directiveTwo = new class() implements Directive, ScoutBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
            {
                return $builder->where('two', $value);
            }
        };
        $directiveNested = new class() implements Directive, ScoutBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
            {
                return $builder->where('nested', $value);
            }
        };
        $directiveIgnored = new class() implements Directive, ScoutBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
            {
                return $builder->where('ignored', $value);
            }
        };
        $directiveWithoutInterface = new class() implements Directive {
            public static function definition(): string
            {
                return '';
            }
        };

        $value = 'value';
        $nested = 'nested value';
        $argumentSet = new ArgumentSet();

        $argumentA = new Argument();
        $argumentA->value = $value;
        $argumentA->directives->push(
            $directiveWithoutInterface,
            $directiveIgnored,
            $directiveTwo,
            $directiveOne,
        );
        $argumentSet->arguments['a'] = $argumentA;

        $nestedArgument = new Argument();
        $nestedArgument->value = $nested;
        $nestedArgument->directives->push(
            $directiveNested,
        );
        $nestedArgumentSet = new ArgumentSet();
        $nestedArgumentSet->arguments['nested'] = $nestedArgument;

        $argumentB = new Argument();
        $argumentB->value = $nestedArgumentSet;
        $argumentSet->arguments['b'] = $argumentB;

        $builder = new ScoutBuilder(new User(), '*');
        $enhancer = new ScoutEnhancer($argumentSet, $builder);
        $builder = $enhancer->enhanceBuilder(static fn (ScoutBuilderDirective $directive): bool => $directive !== $directiveIgnored);

        self::assertSame(
            [
                'two' => $value,
                'one' => $value,
                'nested' => $nested,
            ],
            $builder->wheres,
        );
    }
}
