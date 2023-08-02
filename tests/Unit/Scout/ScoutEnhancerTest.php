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
        $argument = new Argument();
        $argument->value = $value;
        $argument->directives->push(
            $directiveWithoutInterface,
            $directiveIgnored,
            $directiveTwo,
            $directiveOne,
        );
        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['argument'] = $argument;
        $builder = new ScoutBuilder(new User(), '*');
        $enhancer = new ScoutEnhancer($argumentSet, $builder);
        $builder = $enhancer->enhanceBuilder(static fn (ScoutBuilderDirective $directive): bool => $directive !== $directiveIgnored);

        self::assertSame(
            [
                'two' => $value,
                'one' => $value,
            ],
            $builder->wheres,
        );
    }
}
