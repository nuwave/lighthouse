<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::RECTOR_PRESET,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::PHP_80,
        PHPUnitSetList::PHPUNIT_60,
        PHPUnitSetList::PHPUNIT_70,
        PHPUnitSetList::PHPUNIT_80,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
    $rectorConfig->rule(Rector\CodingStyle\Rector\Closure\StaticClosureRector::class);
    $rectorConfig->skip([
        __DIR__ . '/src/Tracing/FederatedTracing/Proto', // Generated code
        __DIR__ . '/tests/database/migrations', // Does not fit autoloader standards
        __DIR__ . '/tests/LaravelPhpdocAlignmentFixer.php', // Copied from Laravel
        Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        Rector\CodeQuality\Rector\Concat\JoinStringConcatRector::class => [
            __DIR__ . '/tests/Integration/OrderBy/OrderByDirectiveTest.php', // Improves clarity
        ],
        Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector::class => [
            __DIR__ . '/src/Testing/TestResponseMixin.php', // mixins are weird
        ],
        Rector\CodingStyle\Rector\Closure\StaticClosureRector::class => [
            __DIR__ . '/src/Testing/TestResponseMixin.php', // Cannot bind an instance to a static closure
        ],
        Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class => [
            __DIR__ . '/tests/Unit/Execution/ResolveInfoTest.php', // Makes method public on purpose
            __DIR__ . '/benchmarks/QueryBench.php', // setUp serves a double purpose here
        ],
        Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector::class, // if($truthy) is fine and very readable
        Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector::class, // unreadable, slow, error prone
        Rector\TypeDeclaration\Rector\FunctionLike\AddReturnTypeDeclarationFromYieldsRector::class, // iterable is fine
        Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector::class, // inefficient
        Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector::class, // does not recognize mockResolver
        Rector\Php80\Rector\FunctionLike\MixedTypeRector::class, // removes useful comments
    ]);
    $rectorConfig->paths([
        __DIR__ . '/benchmarks',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/.php-cs-fixer.php',
        __DIR__ . '/_ide_helper.php',
        __DIR__ . '/rector.php',
    ]);
    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};
