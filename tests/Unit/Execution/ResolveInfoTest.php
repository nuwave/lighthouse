<?php declare(strict_types=1);

namespace Tests\Unit\Execution;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class ResolveInfoTest extends TestCase
{
    public function testApplyArgBuilderDirectives(): void
    {
        $directiveOne = new class() implements Directive, ArgBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
            {
                return $builder->where('one', $value);
            }
        };
        $directiveTwo = new class() implements Directive, ArgBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
            {
                return $builder->where('two', $value);
            }
        };
        $directiveNested = new class() implements Directive, ArgBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
            {
                return $builder->where('nested', $value);
            }
        };
        $directiveIgnored = new class() implements Directive, ArgBuilderDirective {
            public static function definition(): string
            {
                return '';
            }

            public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
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

        $builder = User::query();
        $resolveInfo = new class() extends ResolveInfo {
            /** @phpstan-ignore-next-line no need to call parent `__construct` */
            public function __construct()
            {
                // empty
            }

            public static function applyArgBuilderDirectives(ArgumentSet $argumentSet, Relation|EloquentBuilder|QueryBuilder &$builder, callable $directiveFilter = null): void
            {
                parent::applyArgBuilderDirectives(
                    $argumentSet,
                    $builder,
                    $directiveFilter,
                );
            }
        };

        $resolveInfo::applyArgBuilderDirectives(
            $argumentSet,
            $builder,
            static fn (ArgBuilderDirective $directive): bool => $directive !== $directiveIgnored,
        );

        self::assertSame(
            [
                [
                    'type' => 'Basic',
                    'column' => 'two',
                    'operator' => '=',
                    'value' => $value,
                    'boolean' => 'and',
                ],
                [
                    'type' => 'Basic',
                    'column' => 'one',
                    'operator' => '=',
                    'value' => $value,
                    'boolean' => 'and',
                ],
                [
                    'type' => 'Basic',
                    'column' => 'nested',
                    'operator' => '=',
                    'value' => $nested,
                    'boolean' => 'and',
                ],
            ],
            $builder->toBase()->wheres,
        );
    }
}
