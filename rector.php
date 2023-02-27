<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_90);
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_91);
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_CODE_QUALITY);
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_EXCEPTION);
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD);
    $rectorConfig->import(PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER);
    $rectorConfig->import(PHPUnitSetList::REMOVE_MOCKS);

    $rectorConfig->skip([
        // Does not fit autoloading standards
        __DIR__ . '/tests/database/migrations',

        // It is shorter and more efficient
        CallableThisArrayToAnonymousFunctionRector::class,

        // isset() is nice when moving towards typed properties
        IssetOnPropertyObjectToPropertyExistsRector::class,
    ]);
};
