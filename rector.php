<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Concat\JoinStringConcatRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\Identical\GetClassToInstanceOfRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::PHP_74,
        SetList::PHP_80,
    ]);
    $rectorConfig->sets([
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
        CallableThisArrayToAnonymousFunctionRector::class, // Callable in array form is shorter and more efficient
        IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        JoinStringConcatRector::class => [
            __DIR__ . '/tests/Integration/OrderBy/OrderByDirectiveTest.php', // Improves clarity
        ],
        RemoveExtraParametersRector::class => [
            __DIR__ . '/src/Testing/TestResponseMixin.php', // mixins are weird
        ],
        GetClassToInstanceOfRector::class => [
            __DIR__ . '/src/Schema/Types/Scalars/DateScalar.php', // We need to compare exact classes, not subclasses
        ],
        ExplicitBoolCompareRector::class,
    ]);
};
