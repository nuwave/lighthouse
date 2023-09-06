<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Concat\JoinStringConcatRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\Identical\GetClassToInstanceOfRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\CodingStyle\Rector\Class_\AddArrayDefaultToArrayPropertyRector;
use Rector\CodingStyle\Rector\ClassConst\VarConstantCommentRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\CodingStyle\Rector\ClassMethod\UnSpreadOperatorRector;
use Rector\CodingStyle\Rector\Closure\StaticClosureRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddReturnTypeDeclarationFromYieldsRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::PHP_80,
        PHPUnitSetList::PHPUNIT_80,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_91,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_EXCEPTION,
        PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD,
        PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER,
        PHPUnitSetList::REMOVE_MOCKS,
    ]);
    $rectorConfig->skip([
        __DIR__ . '/tests/database/migrations', // Does not fit autoloading standards
        __DIR__ . '/tests/LaravelPhpdocAlignmentFixer.php', // Copied from Laravel
        CallableThisArrayToAnonymousFunctionRector::class, // Callable in array form is shorter and more efficient
        IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        JoinStringConcatRector::class => [
            __DIR__ . '/tests/Integration/OrderBy/OrderByDirectiveTest.php', // Improves clarity
        ],
        RemoveExtraParametersRector::class => [
            __DIR__ . '/src/Testing/TestResponseMixin.php', // mixins are weird
        ],
        StaticClosureRector::class => [
            __DIR__ . '/src/Testing/TestResponseMixin.php', // Cannot bind an instance to a static closure
        ],
        GetClassToInstanceOfRector::class => [
            __DIR__ . '/src/Schema/Types/Scalars/DateScalar.php', // We need to compare exact classes, not subclasses
        ],
        MakeInheritedMethodVisibilitySameAsParentRector::class => [
            __DIR__ . '/tests/Unit/Execution/ResolveInfoTest.php', // Makes method public on purpose
        ],
        ExplicitBoolCompareRector::class, // if($truthy) is fine and very readable
        EncapsedStringsToSprintfRector::class, // unreadable, slow, error prone
        VarConstantCommentRector::class, // Noisy
        UnSpreadOperatorRector::class, // Breaks some public APIs
        AddArrayDefaultToArrayPropertyRector::class, // Break lazy initialization
        AddReturnTypeDeclarationFromYieldsRector::class, // iterable is fine
        ArrayShapeFromConstantArrayReturnRector::class, // Sometimes too specific in methods that can be overridden
        UnusedForeachValueToArrayKeysRector::class, // inefficient
    ]);
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/.php-cs-fixer.php',
        __DIR__ . '/_ide_helper.php',
        __DIR__ . '/rector.php',
    ]);
    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};
