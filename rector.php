<?php

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\ClassMethod\DateTimeToDateTimeInterfaceRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Core\Configuration\Option;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(SetList::DEAD_CODE);

    $containerConfigurator->import(PHPUnitSetList::PHPUNIT_EXCEPTION);
    $containerConfigurator->import(PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD);
    $containerConfigurator->import(PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER);

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::SKIP, [
        // Does not fit autoloading standards
        __DIR__ . '/tests/database/migrations',

        // References PreLaravel7ExceptionHandler which is not compatible with newer Laravel
        __DIR__ . '/tests/TestCase.php',
        __DIR__ . '/tests/PreLaravel7ExceptionHandler.php',

        // Gets stuck on WhereConditionsBaseDirective for some reason
        __DIR__ . '/src/WhereConditions',

        // It is shorter and more efficient
        CallableThisArrayToAnonymousFunctionRector::class,

        // isset() is nice when moving towards typed properties
        IssetOnPropertyObjectToPropertyExistsRector::class,

        // We just want Carbon
        DateTimeToDateTimeInterfaceRector::class,
    ]);
};
